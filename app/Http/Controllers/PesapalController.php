<?php

namespace App\Http\Controllers;

use App\Models\Ipn;
use App\Models\Order;
use App\Models\OrderSubmissionResponse;
use App\Models\PaymentNotification;
use App\Models\TransactionStatus;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PesapalController extends Controller
{
    /**
     * Generate the authentication token.
     *
     * @return string
     */
    public function generateAuthToken()
    {

        $requestPayload = [
            'consumer_key' => env('PESAPAL_KEY'),
            'consumer_secret' => env('PESAPAL_SECRET'),
        ];
        try {
            $authEndPoint = 'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken';

            $response = Http::post($authEndPoint, $requestPayload);
            $responseData = $response->json();

            if (isset($responseData['error'])) {
                throw new \Exception($responseData['error']['message']);
            }

            $token = $responseData['token'];

            return $token;
        } catch (\Throwable $e) {
            // Handle the error
            return $e;
        }
    }

    /**
     * Register IPN and save the ipn_id.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerIPN()
    {
        $token = $this->generateAuthToken();

        $requestPayload = [
            'url' => env('APP_URL') . '/ipn',
            'ipn_notification_type' => 'POST',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->post('https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN', $requestPayload);

            $responseData = $response->json();

            $ipnId = $responseData['ipn_id'];

            // Save the ipnId in the database for future use
            //Save the IPN_ID received ~ It will be used to when submitting  the order    
            Ipn::create(['ipn_id' => $ipnId]);

            return response()->json(['ipn_id' => $ipnId], 200);
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Submit an order.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitOrder(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'order_id' => 'required|string',
                'currency' => 'required|string',
                'amount' => 'required|numeric',
                'description' => 'required|string',
                'billing_address.email_address' => 'required|email',
                'billing_address.phone_number' => 'nullable|required_without:billing_address.email_address|string',
                'billing_address.first_name' => 'required|string',
                'billing_address.last_name' => 'required|string',

            ]);


            // Extract the order details from the request
            $orderDetails = $request->json()->all();



            // Get the authentication token
            $token = $this->generateAuthToken();

            // Prepare the API endpoint URL
            $endpoint = 'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest';


            // Prepare the request payload
            $payload = [
                'id' => $orderDetails['order_id'],
                'currency' => $orderDetails['currency'],
                'amount' => $orderDetails['amount'],
                'description' => $orderDetails['description'],
                'callback_url' => $orderDetails['callback_url'],
                'notification_id' => $this->getIPNID(), //$orderDetails['notification_id'],
                'billing_address' => [
                    'email_address' => $orderDetails['billing_address']['email_address'],
                    'phone_number' => $orderDetails['billing_address']['phone_number'],
                    'country_code' => isset($orderDetails['billing_address']['country_code']) ? $orderDetails['billing_address']['country_code'] : '',
                    'first_name' => $orderDetails['billing_address']['first_name'],
                    'middle_name' => isset($orderDetails, ['billing_address']['middle_name']) ? $orderDetails['billing_address']['middle_name'] : '',
                    'last_name' => $orderDetails['billing_address']['last_name'],
                    'line_1' =>  isset($orderDetails, ['billing_address']['line_1']) ? $orderDetails['billing_address']['line_1'] : '',
                    'line_2' =>  isset($orderDetails, ['billing_address']['line_2']) ? $orderDetails['billing_address']['line_2'] : '',
                    'city' =>  isset($orderDetails['billing_address']['city']) ? $orderDetails['billing_address']['city'] : '',
                    'state' =>  isset($orderDetails, ['billing_address']['state']) ? $orderDetails['billing_address']['state'] : '',
                    'postal_code' =>  isset($orderDetails, ['billing_address']['postal_code']) ? $orderDetails['billing_address']['postal_code'] : '',
                    'zip_code' =>  isset($orderDetails, ['billing_address']['zip_code']) ? $orderDetails['billing_address']['zip_code'] : '',
                ],
            ];

            // dd($payload);
            // return $payload;



            //Log Submitted order

            $this->logSubmittedOrder($payload);

            // Make the API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post($endpoint, $payload);

            // Check if the request was successful
            if ($response->successful()) {
                // Parse the response JSON
                $responseData = $response->json();

                // Store the order submission response in the database

                $orderSubmission = OrderSubmissionResponse::create([
                    'order_tracking_id' => $responseData['order_tracking_id'],
                    'merchant_reference' => $responseData['merchant_reference'],
                    'redirect_url' => $responseData['redirect_url'],
                    'error' => $responseData['error'],
                    'status' => $responseData['status'],
                ]);

                //Update Order after submission
                $order = Order::where('order_id', $orderDetails['order_id'])->first();
                $order->update([
                    'order_tracking_id' => $orderSubmission->order_tracking_id,
                    'merchant_reference' => $orderSubmission->merchant_reference,
                    // 'redirect_url' => $orderSubmission->redirect_url,
                    'error' => $orderSubmission->error,
                ]);


                // Return the order submission response
                return response()->json([
                    'order_tracking_id' => $orderSubmission->order_tracking_id,
                    'merchant_reference' => $orderSubmission->merchant_reference,
                    'redirect_url' => $orderSubmission->redirect_url,
                    'error' => $orderSubmission->error,
                    'status' => $orderSubmission->status,
                ]);
            } else {
                // Handle the error response
                $errorMessage = $response->json('error.message');
                return response()->json(['error' => $errorMessage], $response->status());
            }
        } catch (Throwable $th) {
            Storage::disk('local')->prepend('orderSub.json', $th->getMessage());
            // Handle any exceptions that occur during the API request
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function logSubmittedOrder($orderDetails)
    {
        Storage::disk('local')->prepend('order.json', json_encode($orderDetails));
        try {
            $order = new Order();
            $order->order_id = $orderDetails['id'];
            $order->trandate = Carbon::now()->format('Y-m-d H:m:s');
            $order->description = $orderDetails['description'];
            $order->currency = $orderDetails['currency'];
            $order->amount = $orderDetails['amount'];
            $order->first_name = $orderDetails['billing_address']['first_name'];
            $order->last_name = $orderDetails['billing_address']['last_name'];
            $order->cellphone = $orderDetails['billing_address']['phone_number'];
            $order->save();
        } catch (\Throwable $th) {
            Storage::disk('local')->prepend('orderLog.json', $th->getMessage());
            // throw $th;
        }
    }


    /**
     * Get transaction status.
     *
     * @param  string  $orderTrackingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactionStatus($orderTrackingId)
    {
        $token = $this->generateAuthToken();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get("https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId={$orderTrackingId}");

            $responseData = $response->json();

            //Log Status Response
            $this->logTransactionStatus($responseData);

            //Find order via merchant reference & Update the paymentstatus
            $order = Order::where('merchant_reference', $responseData['merchant_reference'])->first();
            $order->update(
                [
                    'status' => $this->getStatus($responseData['status_code']),
                    'payment_status_description' => $responseData['payment_status_description'],
                    'payment_status_info' => $responseData['description']

                ]
            );


            return response()->json(['message' => 'IPN Received Successfully'], 200);
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function logTransactionStatus($responseData)
    {
        try {
            //Log Status Response
            $data = $responseData;
            $transactionStatus = new TransactionStatus();
            $transactionStatus->payment_method = $data['payment_method'];
            $transactionStatus->amount = $data['amount'];
            $transactionStatus->created_date = $data['created_date'];
            $transactionStatus->confirmation_code = $data['confirmation_code'];
            $transactionStatus->payment_status_description = $data['payment_status_description'];
            $transactionStatus->description = $data['description'];
            $transactionStatus->message = $data['message'];
            $transactionStatus->payment_account = $data['payment_account'];
            $transactionStatus->call_back_url = $data['call_back_url'];
            $transactionStatus->status_code = $data['status_code'];
            $transactionStatus->merchant_reference = $data['merchant_reference'];
            $transactionStatus->payment_status_code = $data['payment_status_code'];
            $transactionStatus->currency = $data['currency'];
            $transactionStatus->error = json_encode($data['error']);
            $transactionStatus->status = $data['status'];
            $transactionStatus->save();
        } catch (\Throwable $th) {
            Storage::disk()->prepend('logTranstat.json', $th->getMessage());
        }
    }

    /**
     * Handle IPN callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleIPNCallback(Request $request)
    {
        try {
            $payload = $request->all();
            Storage::disk()->prepend('ipnCallback.json', $payload);

            // [
            //     "orderNotificationType"=>"IPNCHANGE",
            //     "orderTrackingId"=>"d0fa69d6-f3cd-433b-858e-df86555b86c8",
            //     "orderMerchantReference"=>"1515111111",
            //     "status"=>200
            // ]

            $ipn = PaymentNotification::create(
                [
                    "order_notification_type" => $payload['orderNotificationType'],
                    "order_tracking_id" => $payload['orderTrackingId'],
                    "order_merchant_reference" => $payload['orderMerchantReference'],
                    "status" => $payload['status'],
                ]
            );
            if ($ipn->order_notification_type == 'IPNCHANGE' && $ipn->order_merchant_reference != '') {
                $this->getTransactionStatus($ipn->order_tracking_id);
            }


            return response()->json(['message' => 'IPN callback processed successfully'], 200);
        } catch (\Throwable $th) {
            Storage::disk('local')->prepend('IPNerror.json', $th->getMessage());
            return response()->json(['message' => $th->getMessage()], 200);
        }
    }

    /**
     * Get IPN list.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIPNList()
    {
        $token = $this->generateAuthToken();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://cybqa.pesapal.com/pesapalv3/apiURLSetup/GetIpnList');

            $responseData = $response->json();


            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getStatus($statusCode)
    {
        switch ($statusCode) {
            case 0:
                return 'INVALID';
            case 1:
                return 'COMPLETED';
            case 2:
                return 'FAILED';
            case 3:
                return 'REVERSED';
            default:
                return 'UNKOWN';
        }
    }
    public function getIPNID()
    {
        $ipn = Ipn::first();
        return $ipn->ipn_id;
    }
}
