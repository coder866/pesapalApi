
# pesapalApi


/// PesaPal API 3.0 Implementtation

 ///For Successfull transactions all requests to PesaPal API must be authenticated with a bearer token;

/// AUthentication Request Sample


// curl --location 'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' \
// --header 'Content-Type: application/json' \
// --header 'Accept: application/json' \
// --data '{
//   "consumer_key": "xxx",
//   "consumer_secret": "xxx"
// }'



/// Authentication Response Sample

// {
//     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJodHRwOi8vc2NoZW1hcy5taWNyb3NvZnQuY29tL3dzLzIwMDgvMDYa",
//         "expiryDate": "2021-08-26T12:29:30.5177702Z",
//             "error": null,
//                 "status": "200",
//                     "message": "Request processed successfully"
// } 

//To have pesapal api send payment notifications to your system you need to regisster your back end callback as below


/// IPN REGISTRATION REQUEST

// curl --location 'https://cybqa.pesapal.com/pesapalv3/api/URLSetup/RegisterIPN' \
// --header 'Accept: application/json' \
// --header 'Content-Type: application/json' \
// --header 'Authorization: Bearer $token \
// --data '{
//     "url": env('APP_URL')."/ipn",
//     "ipn_notification_type": "POST"
// }'

/// IPN REGISTRATION RESPONSE

// {
//     "url": "https://www.myapplication.com/ipn",
//         "created_date": "2022-03-03T17:29:03.7208266Z",
//             "ipn_id": "e32182ca-0983-4fa0-91bc-c3bb813ba750",
//                 "error": null,
//                     "status": "200"
// }  


// TODO
//a) Generate IPN Registration Implementation on successfull registration receive the response payload extract "ipn_id" and store it in the database.
//b) Create a method for retrieving the stored "ipn_id" 
//Remember ipn_id will be used when submitting orders.


/// ORDER SUBMISION REQUEST SAMPLE

// curl --location 'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' \
// --header 'Content-Type: application/json' \
// --header 'Authorization: Bearer $token \
// --data-raw '
{
//     "id": "TEST1515111110",
//     "currency": "KES",
//     "amount": 100.00,
//     "description": "Payment description goes here",
//     "callback_url": "https://www.myapplication.com/response-page",
//     "notification_id": "fe078e53-78da-4a83-aa89-e7ded5c456e6",
//     "billing_address": {
//         "email_address": "john.doe@example.com",
//         "phone_number": null,
//         "country_code": "",
//         "first_name": "John",
//         "middle_name": "",
//         "last_name": "Doe",
//         "line_1": "",
//         "line_2": "",
//         "city": "",
//         "state": "",
//         "postal_code": null,
//         "zip_code": null
//     }
// }'

//Note: "notification_id" field in the request, is where the stored "ipn_id" goes. Remember this.


// Generate a migration to store ORder Submission Response  

// {
//     "order_tracking_id": "b945e4af-80a5-4ec1-8706-e03f8332fb04",
//         "merchant_reference": "TEST1515111119",
//             "redirect_url": "https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?OrderTrackingId=b945e4af-80a5-4ec1-8706-e03f8332fb04",
//                 "error": null,
//                     "status": "200"
// }

//Implement Get Transaction Status Request as follows 

// curl--location 'https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId=xxxxxxxxxxxxxx' \
// --header 'Accept: application/json' \
// --header 'Content-Type: application/json' \
// --header 'Authorization: Bearer <token>'


/// Transaction Status Response

// {
//     "payment_method": "Visa",
//         "amount": 100,
//             "created_date": "2022-04-30T07:41:09.763",
//                 "confirmation_code": "6513008693186320103009",
//                     "payment_status_description": "Failed",
//                         "description": "Unable to Authorize Transaction.Kindly contact your bank for assistance",
//                             "message": "Request processed successfully",
//                                 "payment_account": "476173**0010",
//                                     "call_back_url": "https://test.com/?OrderTrackingId=7e6b62d9-883e-440f-a63e-e1105bbfadc3&OrderMerchantReference=1515111111",
//                                         "status_code": 2,
//                                             "merchant_reference": "1515111111",
//                                                 "payment_status_code": "",
//                                                     "currency": "KES",
//                                                         "error": {
//         "error_type": null,
//             "code": null,
//                 "message": null,
//                     "call_back_url": null
//     },
//     "status": "200"
// }    


/// Status Code and Description

// status_code	Pesapal status code representing the payment_status_description.
// 0 - INVALID
// 1 - COMPLETED
// 2 - FAILED
// 3 - REVERSED


//Implement the following on IPN CallBack

//Receive payload from pesapal extract "orderMerchantReference"
//search order and update it with the "orderTrackingId" from the response as below;

//Sample Payload to Registered IPN CAll Back

// Sample IPN JSON Response String: 
// { 
// "orderNotificationType": "IPNCHANGE", 
// "orderTrackingId": "d0fa69d6-f3cd-433b-858e-df86555b86c8", 
// "orderMerchantReference": "1515111111",
//  "status": 200 
// }


//To list all the callbacks registered with pesapal you make a Get IPN LIST request as below ;

/// Get IPN LIST REQUEST SAMPLE

// curl --location 'https://cybqa.pesapal.com/pesapalv3/apiURLSetup/GetIpnList' \
// --header 'Authorization: Bearer <token>'

/// IPN LIST Response

// [
//     {
//         "url": "https://www.myapplication.com/ipn",
//         "created_date": "2022-03-03T17:29:03.7208266Z",
//         "ipn_id": "e32182ca-0983-4fa0-91bc-c3bb813ba750",
//         "error": null,
//         "status": "200"
//     },
//     {
//         "url": "https://ipn.myapplication.com/application2",
//         "created_date": "2021-12-05T04:23:45.5509243Z",
//         "ipn_id": "c3bb813ba750-0983-4fa0-91bc-e32182ca",
//         "error": null,
//         "status": "200"
//     }
// ]


 


