<?php

namespace App\Http\Controllers;

use App\Models\OrderSubmissionResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        // Validate the request data
        $request->validate([
            'id' => 'required|string',
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
            'id' => $orderDetails['id'],
            'currency' => $orderDetails['currency'],
            'amount' => $orderDetails['amount'],
            'description' => $orderDetails['description'],
            'callback_url' => $orderDetails['callback_url'],
            'notification_id' => 'b47aaa49-1fd5-48c6-9b77-e02116131ab4', //$orderDetails['notification_id'],
            'billing_address' => [
                'email_address' => $orderDetails['billing_address']['email_address'],
                'phone_number' => $orderDetails['billing_address']['phone_number'],
                'country_code' => $orderDetails['billing_address']['country_code'],
                'first_name' => $orderDetails['billing_address']['first_name'],
                'middle_name' => $orderDetails['billing_address']['middle_name'],
                'last_name' => $orderDetails['billing_address']['last_name'],
                'line_1' => $orderDetails['billing_address']['line_1'],
                'line_2' => $orderDetails['billing_address']['line_2'],
                'city' => $orderDetails['billing_address']['city'],
                'state' => $orderDetails['billing_address']['state'],
                'postal_code' => $orderDetails['billing_address']['postal_code'],
                'zip_code' => $orderDetails['billing_address']['zip_code'],
            ],
        ];

        // Make the API request
        try {
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
        } catch (Exception $e) {
            // Handle any exceptions that occur during the API request
            return response()->json(['error' => $e->getMessage()], 500);
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

            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            // Handle the error
            return response()->json(['error' => $e->getMessage()], 500);
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
        $payload = $request->all();

        $orderMerchantReference = $payload['orderMerchantReference'];

        // Search for the order based on the orderMerchantReference and update it with the orderTrackingId

        //TODO  updating the order
        // $order = Order::where('merchant_reference', $orderMerchantReference)->first();
        // if ($order) {
        //     $order->order_tracking_id = $payload['orderTrackingId'];
        //     $order->save();
        // }

        return response()->json(['message' => 'IPN callback processed successfully'], 200);
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
}
