<?php

namespace Xnjuguna\Pesapal\Controllers;

use App\Http\Controllers\Controller;
use Xnjuguna\Pesapal\Models\PesapalOrderSubmissionResponse;
use Xnjuguna\Pesapal\Models\PesapalPaymentNotification;
use Xnjuguna\Pesapal\Models\PesapalTransactionStatus;
use Xnjuguna\Pesapal\Models\PesapalOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;


class PesapalPaymentsController extends Controller
{
   
    /**
     * 
     * List Orders
     */

    public function getOrdersList()
    {
        try {
            $cacheKey = "pesapal_orders";

            $orders = Cache::remember($cacheKey, now()->addMinutes(10), function () {
                return PesapalOrder::OrderBy('created_at', 'desc')->get();
            });
            return Response()->json($orders, 200);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    /**
     * Generate the authentication token.
     *
     * @return string
     */
    public function generateAuthToken()
    {

        $requestPayload = [
            'consumer_key' => Config('pesapal.consumer_key'),
            'consumer_secret' => Config('pesapal.consumer_secret'),
        ];
        try {
            $authEndPoint = $this->api_link() . '/Auth/RequestToken';
            dd($authEndPoint);

            $response = Http::post($authEndPoint, $requestPayload);
            $responseData = $response->json();

            if (isset($responseData['error'])) {
                throw new \Exception($responseData['error']['message']);
            }

            $token = $responseData['token'];

            return $token;
        } catch (\Throwable $th) {
            // Handle the error
            throw $th;
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
            'url' => config('pesapal.ipn_url'),
            'ipn_notification_type' => 'POST',
        ];

        try {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->post($this->api_link() . '/URLSetup/RegisterIPN', $requestPayload);

            $responseData = $response->json();

            $ipnId = $responseData['ipn_id'];

            // Save the ipnId in the database for future use
            //Save the IPN_ID received ~ It will be used to when submitting  the order    

            $this->updateIpnid($ipnId);

            return response()->json(['ipn_id' => $ipnId], 200);
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Set IPN ID on .env
     * 
     */

    public function updateIpnid(String $ipnid)
    {
        try {
            // Get the path to the .env file
            $envFilePath = base_path('.env');

            $envContents = File::get($envFilePath);
            // Replace the existing IPN_ID value or add it if not present
            if (strpos($envContents, 'IPN_ID') !== false) {
                $envContents = preg_replace('/IPN_ID=.*/', "IPN_ID=$ipnid", $envContents);
            } else {
                $envContents .= "\nIPN_ID=$ipnid\n";
            }

            // Write the updated contents back to the .env file
            File::put($envFilePath, $envContents);

            return response()->json(['message' => 'IPN_ID updated successfully']);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /** 
     * Fetch IPN_ID
     * 
     */

    public function getIPNID()
    {
        return config('pesapal.ipn_id');
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
            $validator = Validator::make($request->all(), [
                'id' => 'required|string', //Required
                'currency' => 'required|string',  //Required
                'amount' => 'required|numeric', //Required
                'description' => 'required|string',  //Required
                'billing_address.email_address' => 'nullable|email|required_without:billing_address.phone_number',  //Required if Cellphone is Missing
                'billing_address.phone_number' => 'nullable|string|required_without:billing_address.email_address', //Required if Email is Missing
                'billing_address.first_name' => 'nullable|string',
                'billing_address.last_name' => 'nullable|string',
                'account_number' => 'nullable|string',  
                // 'subscription_details.start_date' => 'required_with:account_number|string',  //Required
                // 'subscription_details.end_date' => 'required_with:account_number|string',  //Required
                // 'subscription_details.frequency' => 'required_with:account_number|string',  //Required

            ]);


            if ($validator->fails()) {
                $validationCatch = [
                    'order_tracking_id' => '',
                    'merchant_reference' => '',
                    'redirect_url' => '',
                    'error' => $validator->errors(),
                    'status' => 422,
                ];

                return response()->json($validationCatch, 500);
            }


            // Extract the order details from the request
            $orderDetails = $validator->validated();



            // Get the authentication token
            $token = $this->generateAuthToken();

            // Prepare the API endpoint URL
            $endpoint = $this->api_link() . '/Transactions/SubmitOrderRequest';



            // Prepare the request payload
            $payload = [
                'id' => $orderDetails['id'], //Required
                'currency' => $orderDetails['currency'], //required
                'amount' => $orderDetails['amount'], //required
                'description' => $orderDetails['description'], //required
                'callback_url' => config('pesapal.callback_url'),  //required
                'notification_id' => $this->getIPNID(),  //required
                'billing_address' => [
                    'email_address' => $orderDetails['billing_address']['email_address'], //Required if cellphone is missing
                    'phone_number' => $orderDetails['billing_address']['phone_number'], //Required if email is missing
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
                ]
                // 'account_number' => $orderDetails['account_number']
                // "subscription_details"=> [
                //             "start_date"=>Carbon::parse($orderDetails['subscription_details']['start_date'])->format('jS M Y'),
                //             "end_date"=> Carbon::parse($orderDetails['subscription_details']['end_date'])->format('jS M Y'),
                //             "frequency"=> $orderDetails['subscription_details']['frequency']
                // ]
                    


            ];
            // if(isset($orderDetails['subscription_details'])){
            // 'subscription_details'=array(
            //     'start_date' => "string",
            //     'end_date' => "string",
            //     'frequency' => "string",
            // );
            // }

            // dd($payload);
            // return response()->json($payload);


            Storage::disk('local')->prepend('orderPAYLOAD.json', json_encode($payload));

            //Log Submitted order

            $this->logSubmittedOrder($payload,$request->ordertype);

            // Make the API request

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->post($endpoint, $payload);


            // dd($response->json());
            Storage::disk('local')->prepend('orderRESP.json', $response->successful() ? 'Successfull Submission' : 'Failure ');
            // Check if the request was successful
            if ($response->successful()) {
                // Parse the response JSON
                $responseData = $response->json();

                if (isset($responseData['error'])) {
                    Storage::disk('local')->prepend('orderRESP.json', 'FOUND ERRORS: ' . $responseData['error']['message']);
                    $errorPayLoad = [
                        'order_tracking_id' => 'ERROR-TYPE:' . json_encode($responseData['error']),
                        'merchant_reference' => 'ERROR-CODE:' . json_encode($responseData['error']),
                        'redirect_url' => '',
                        'error' => 'ERROR-MSG:' . $responseData['error']['message'],
                        'status' => "500",
                    ];
                    // Handle the error response
                    Storage::disk('local')->prepend('orderSub.json', json_encode($errorPayLoad));

                    return response()->json($errorPayLoad, 200);
                }
                Storage::disk('local')->prepend('orderRESP.json', 'NO ERROR-Recording Response');
                // Store the order submission response in the database
                $orderSubmission = PesapalOrderSubmissionResponse::create([
                    'order_tracking_id' => $responseData['order_tracking_id'],
                    'merchant_reference' => $responseData['merchant_reference'],
                    'redirect_url' => $responseData['redirect_url'],
                    'error' => $responseData['error'],
                    'status' => $responseData['status'],
                ]);

                Storage::disk('local')->prepend('orderRESP.json', 'After-Recording Response');
                //Update Order after submission

                $order = PesapalOrder::where('order_id', $orderDetails['id'])->first();

                $order->update([
                    'order_tracking_id' => $orderSubmission->order_tracking_id,
                    'merchant_reference' => $orderSubmission->merchant_reference,
                    // 'redirect_url' => $orderSubmission->redirect_url,
                    'error' => $orderSubmission->error,
                ]);

                Storage::disk('local')->prepend('orderRESP.json', 'Updated ORDER');

                $responsePayLoad = [
                    'order_tracking_id' => $orderSubmission->order_tracking_id,
                    'merchant_reference' => $orderSubmission->merchant_reference,
                    'redirect_url' => $orderSubmission->redirect_url,
                    'error' => $orderSubmission->error,
                    'status' => $orderSubmission->status,
                ];

                Storage::disk('local')->prepend('orderRESP.json', 'responsePayLoad:' . json_encode($responsePayLoad));
                // Return the order submission response
                return response()->json($responsePayLoad);
            } else {
                $responseData = $response->json();
                $errorPayLoad = [
                    'order_tracking_id' => $responseData['order_tracking_id'],
                    'merchant_reference' => $responseData['merchant_reference'],
                    'redirect_url' => $responseData['redirect_url'],
                    'error' => $response->json('error.message'),
                    'status' => $responseData['status'],
                ];
                // Handle the error response

                Storage::disk('local')->prepend('orderRESP.json', 'ErrorRESPONSE:' . json_encode($errorPayLoad));

                return response()->json($errorPayLoad);
            }
        } catch (\Throwable $th) {

            $catchPayLoad = [
                'order_tracking_id' => '',
                'merchant_reference' => '',
                'redirect_url' => '',
                'error' => $th->getMessage(),
                'status' => "500",
            ];
            // Handle the error response
            Storage::disk('local')->prepend('orderSub.json', json_encode($catchPayLoad));
            // Handle any exceptions that occur during the API request
            return response()->json($catchPayLoad);
        }
    }

    /**
     * 
     * Receive Payment Completion Response
     * 
     */
    public function paymentCompleted(Request $request)
    {

        try {
            $payload = $request->all();
 

            $contains = Arr::hasAny($payload, ['OrderTrackingId', 'OrderMerchantReference']);
            // OrderNotificationType
            if (isset($payload['OrderTrackingId'])) {

               $tranStatus= $this->getPesapalTransactionStatus($payload['OrderTrackingId']);
            //    $this->createSubscription($tranStatus['order']);
            }
               
            $statusResp=json_decode($tranStatus->getContent(),true);
            $message=$statusResp['message'];
            $order=isset($statusResp['order'])?$statusResp['order']:PesapalOrder::where(['order_tracking_id' => $payload['OrderTrackingId']])->first();

            $response =
                [
                    "order_notification_type" => isset($payload['OrderNotificationType'])?$payload['OrderNotificationType']:'MISSING',
                    "order_tracking_id" => $payload['OrderTrackingId'],
                    "order_merchant_reference" => $payload['OrderMerchantReference'],
                    "message" => 'Payment Completed Successfully',
                    "status" => 1,
                ];

            return response()->json($response);

            
        } catch (\Throwable $th) {
            Storage::disk()->prepend('paytCOMPERROR.json', json_encode($th->getMessage()));

            $response =
                [
                    "order_notification_type" => '',
                    "order_tracking_id" => '',
                    "order_merchant_reference" => '',
                    "message" => $th->getMessage(),
                    "status" => 0,
                ];

            return response()->json($response);
        }
    }


    public function logSubmittedOrder($orderDetails,$ordertype)
    {
        Storage::disk('local')->prepend('order.json', json_encode($orderDetails));
        try {
            $order = new PesapalOrder();
            $order->order_id = $orderDetails['id'];
            $order->trandate = Carbon::now()->format('Y-m-d H:m:s');
            $order->description = $orderDetails['description'];
            $order->currency = $orderDetails['currency'];
            $order->amount = $orderDetails['amount'];
            $order->first_name = $orderDetails['billing_address']['first_name'];
            $order->last_name = $orderDetails['billing_address']['last_name'];
            $order->cellphone = $orderDetails['billing_address']['phone_number'];
            $order->ordertype = $ordertype;
            // $order->account_number = $orderDetails['account_number'];
            // $order->subscription_plan = $orderDetails['subscription_details']['frequency'];
            // $order->subscription_start = Carbon::parse($orderDetails['subscription_details']['start_date'])->format('Y-m-d');
            // $order->subscription_end =  Carbon::parse($orderDetails['subscription_details']['end_date'])->format('Y-m-d');

            $order->save();
        } catch (\Throwable $th) {
            Storage::disk('local')->prepend('orderLog.json', $th->getMessage());
            throw $th;
        }
    }


    /**
     * Get transaction status.
     *
     * @param  string  $orderTrackingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPesapalTransactionStatus($orderTrackingId)
    {
        $token = $this->generateAuthToken();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->api_link() . "/Transactions/GetTransactionStatus?orderTrackingId={$orderTrackingId}");

            if ($response->successful()) {

                $responseData = $response->json();
            //    return $responseData;
                //Log Status Response
                // $this->logPesapalTransactionStatus($responseData);

                //Find order via order_tracking_id & Update the paymentstatus
                $order = PesapalOrder::where(['order_tracking_id' => $orderTrackingId])->first();
                $order->update(
                    [
                        'status' => $this->getStatus($responseData['status_code']),
                        'payment_method' => $responseData['payment_method'],
                        'payment_date' => Carbon::parse($responseData['created_date'])->format('Y-m-d H:m:s'),
                        'payment_confirmation_code' => $responseData['confirmation_code'],
                        'payment_description' => $responseData['description'],
                        'payment_message' => $responseData['message'],
                        'payment_account' => $responseData['payment_account'],
                        'payment_status_code' => $responseData['payment_status_code'],
                        'payment_status_description' => $responseData['payment_status_description'],
                        'error' => $responseData['error'],

                    ]
                );


                return response()->json(['message' => 'IPN Received Successfully','order'=>$order], 200);
            } else {
                // Log::error("Error: " .$response->body() );
                Storage::disk()->prepend('statusERROR.json', json_encode($response->json()));

                return response()->json(['message' => $response->json(),'order'=>null], 200);
            }
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['message' => $e->getMessage(),'order'=>null], 500);
        }
    }

    public function logPesapalTransactionStatus($data)
    {
        Storage::disk()->prepend('transtatusDATA.json', json_encode($data));
        try {
            //Log Status Response
            PesapalTransactionStatus::create(
                [
                    'payment_method' => $data['payment_method'],
                    'amount' => $data['amount'],
                    'created_date' => $data['created_date'],
                    'confirmation_code' => $data['confirmation_code'],
                    'payment_status_description' => $data['payment_status_description'],
                    'description' => $data['description'] ?? '',
                    'message' => $data['message'],
                    'payment_account' => $data['payment_account'],
                    'call_back_url' => $data['call_back_url'],
                    'status_code' => $data['status_code'],
                    'merchant_reference' => $data['merchant_reference'],
                    'payment_status_code' => $data['payment_status_code'],
                    'currency' => $data['currency'],
                    'error' => json_encode($data['error']),
                    'status' => $data['status']
                ]
            );
        } catch (\Throwable $th) {

            Storage::disk()->prepend('logTranstat.json', $th->getMessage());

            throw $th;
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
            Storage::disk()->prepend('ipnCallback.json', json_encode($payload));

            // [
            //     "orderNotificationType"=>"IPNCHANGE",
            //     "orderTrackingId"=>"d0fa69d6-f3cd-433b-858e-df86555b86c8",
            //     "orderMerchantReference"=>"1515111111",
            //     "status"=>200
            // ]


            //
            $ipn = PesapalPaymentNotification::create(
                [
                    "order_notification_type" => isset($payload['OrderNotificationType'])?$payload['OrderNotificationType']:'NotificationType MIssing',
                    "order_tracking_id" => $payload['OrderTrackingId'],
                    "order_merchant_reference" => $payload['OrderMerchantReference'],
                    "status" => 0,
                ]
            );
            if (isset($payload['OrderNotificationType'])&&isset($payload['OrderNotificationType']) == 'IPNCHANGE' && $payload['OrderTrackingId'] != '') {
                $this->getPesapalTransactionStatus($payload['OrderTrackingId']);
            }


            return response()->json(['message' => 'IPN callback processed successfully'], 200);
        } catch (\Throwable $th) {

            Storage::disk('local')->prepend('IPNerror.json', $th->getMessage());

            return response()->json(['message' => $th->getMessage()], 500);
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
            ])->get($this->api_link() . '/URLSetup/GetIpnList');

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

    /**
     * Get API path
     * @param null $path
     * @return string
     */
    public function api_link($path = null)
    {
        $live = 'https://pay.pesapal.com/v3/api';
        $demo = 'https://cybqa.pesapal.com/pesapalv3/api';
        return (config('pesapal.env') == 'production' ? $live : $demo) . $path;
    }
}


// https://www.scribbr.com/plagiarism-checker/
// https://www.duplichecker.com/
// https://www.check-plagiarism.com/