<?php
namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderShippingData;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use validator;

class OrderShippingController extends Controller
{
    protected $shipRocketBaseUrl;
    protected $shipRocketToken;
    public function __construct()
    {
        $shipType = BusinessSetting::where('type', 'shipping_type')->first()->value; //'ship_rocket'
        $this->shipRocketBaseUrl = env('SHIPROCKET_BASEURL');
        if ($shipType != "ship_rocket") {
            $response['status'] = false;
            $response['message'] = "Dynamic shipping method is not set.";
            echo json_encode($response);exit;
        }

        $shiprocketTokenSetting = BusinessSetting::where('type', 'shiprocket_API_token')->first();
        if (empty($shiprocketTokenSetting->value)) {
            $response['status'] = false;
            $response['message'] = "Ship rocket token config not set yet";
            return response()->json($response);
        }
        $this->shipRocketToken = $shiprocketTokenSetting->value;
    }

    public function createShippingOrder(Request $request)
    {
        $response = [];
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            flash(translate('Order not found.'))->error();
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }

        $orderCode = !empty($orderInfo->code) ? $orderInfo->code : null;
        $orderDate = !empty($orderInfo->created_at) ? date('Y-m-d H:i', strtotime($orderInfo->created_at)) : null;

        $orderShipingData = Order::where('id', $orderId)
            ->where('code', $orderCode)
            ->first();
        if (!empty($orderShipingData)) {
            $response['status'] = 1;
            $response['message'] = "Shipping order is already placed.";
            flash(translate('Shipping order is already placed.'))->error();
            return response()->json($response);
        }

        $shippingAddress = !empty($orderInfo->shipping_address) ? json_decode($orderInfo->shipping_address, 1) : [];

        $billingCustomerName = !empty($shippingAddress['name']) ? $shippingAddress['name'] : null;
        $firstName = $lastName = "";
        $billingCustomerNameArr = explode(" ", $billingCustomerName);
        $firstName = !empty($billingCustomerNameArr[0]) ? $billingCustomerNameArr[0] : "No Name";
        $lastName = !empty($billingCustomerNameArr[1]) ? $billingCustomerNameArr[1] : "No Name";
        $billingCity = !empty($shippingAddress['city']) ? $shippingAddress['city'] : null;
        $billingPincode = !empty($shippingAddress['postal_code']) ? $shippingAddress['postal_code'] : null;
        $billingState = "Gujarat";
        $billingAddress = !empty($shippingAddress['address']) ? $shippingAddress['address'] : null;
        $billingCountry = !empty($shippingAddress['country']) ? $shippingAddress['country'] : null;
        $billingEmail = !empty($shippingAddress['email']) ? $shippingAddress['email'] : null;
        $billingPhone = !empty($shippingAddress['phone']) ? $shippingAddress['phone'] : null;
        $shippingIsBilling = true;
        $orderProductDetail = OrderDetail::where('order_id', $orderId)->get();
        $orderItemsData = [];
        foreach ($orderProductDetail as $odpkey => $odpval) {
            $productId = $odpval->product_id;
            $productVariation = !empty($odpval->variation) ? $odpval->variation : null;
            $quantity = !empty($odpval->quantity) ? $odpval->quantity : 1;

            $productcInfo = Product::where('id', $productId)->first();
            $tax = $productcInfo->tax;
            $sellingPrice = $productcInfo->price;
            $productName = $productcInfo->name;

            $productcStockInfo = ProductStock::where('product_id', $productId)->where('variant', $productVariation)->first();
            $productSku = $productcStockInfo->sku;

            $data = [];
            $data['name'] = $productName;
            $data['sku'] = $productSku;
            $data['units'] = $quantity;
            $data['selling_price'] = $sellingPrice;
            $data['discount'] = 0;
            $data['tax'] = $tax;
            $orderItemsData[] = $data;
        }
        $paymentMethod = (!empty($orderInfo->payment_type) && $orderInfo->payment_type == "cash_on_delivery") ? "COD" : "Prepaid";
        $shippingCharges = !empty($orderInfo->ship_rocket_shipping_charge) ? $orderInfo->ship_rocket_shipping_charge : 0;
        $subTotal = 0;
        $grandTotal = !empty($orderInfo->grand_total) ? $orderInfo->grand_total : null;
        if ($grandTotal) {
            $subTotal = $grandTotal - $shippingCharges;
        }
        $response = [];
        $orderData = Order::where('code', $orderCode)->first();
        if (!empty($orderData)) {
            flash(translate('Order already has been placed.'))->error();
            $response['status'] = false;
            $response['message'] = "Order already has been placed.";
            return response()->json($response);
        }

        $requestArr = [];
        $requestArr['order_id'] = $orderCode;
        $requestArr['order_date'] = $orderDate;
        $requestArr['billing_customer_name'] = $firstName;
        $requestArr['billing_city'] = $billingCity;
        $requestArr['billing_pincode'] = $billingPincode;
        $requestArr['billing_state'] = $billingState;
        $requestArr['billing_country'] = $billingCountry;
        $requestArr['billing_email'] = $billingEmail;
        $requestArr['billing_phone'] = $billingPhone;
        $requestArr['shipping_is_billing'] = $shippingIsBilling;
        $requestArr['order_items'] = $orderItemsData;
        $requestArr['payment_method'] = $paymentMethod;
        $requestArr['shipping_charges'] = $shippingCharges;
        $requestArr['billing_last_name'] = $lastName;
        $requestArr['billing_address'] = $billingAddress;
        $requestArr['sub_total'] = $subTotal;
        $requestArr['length'] = 1;
        $requestArr['breadth'] = 1;
        $requestArr['height'] = 1;
        $requestArr['weight'] = 1;

        $url = $this->shipRocketBaseUrl . "orders/create/adhoc";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);

        $orderPlaceResponse = json_decode($result, 1);
        if (!empty($orderPlaceResponse['status_code']) && $orderPlaceResponse['status_code'] == 422) {
            flash(translate('' . $orderPlaceResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPlaceResponse['message'];
            return response()->json($response);
        }

        if (!empty($orderPlaceResponse['status_code']) && $orderPlaceResponse['status_code'] == 400) {
            flash(translate('' . $orderPlaceResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPlaceResponse['message'];
            return response()->json($response);
        }

        $shipmentId = !empty($orderPlaceResponse['shipment_id']) ? $orderPlaceResponse['shipment_id'] : null;
        $shippingOrderId = !empty($orderPlaceResponse['order_id']) ? $orderPlaceResponse['order_id'] : null;
        /* add into table start */
        $response = [];
        $orderShipData = new OrderShippingData;
        $orderShipData->original_order_id = $orderId;
        $orderShipData->code = $orderCode;
        $orderShipData->shipping_order_id = $shippingOrderId;
        $orderShipData->shipment_id = $shipmentId;
        $orderShipData->order_status = 1;
        $orderShipData->order_place_response = $result;
        $orderShipData->shipping_place_order_date = date('Y-m-d H:i:s');
        if ($orderShipData->save()) {
            $this->updateOrderStatus($orderId, 'confirmed');
            $response['status'] = 1;
            $response['message'] = "Order has been shipped.";
            flash(translate('Order has been shipped'))->error();
        } else {
            flash(translate('Something went wrong while placing order'))->error();
            $response['status'] = false;
            $response['message'] = "Something went wrong while placing order.";
        }
        /* add into table end */
        return response()->json($response);
        exit;
    }

    public function assignAwbToShipping(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            flash(translate('Enter valid input'))->error();
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            return response()->json($response);
        }

        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            flash(translate('Order not found'))->error();
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }

        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            flash(translate('Shipping order still not placed.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }

        if ($orderShipingData->order_status != 1) {
            flash(translate('Order is not valid'))->error();
            $response['status'] = false;
            $response['message'] = "Order is not valid";
            return response()->json($response);
        }

        if ($orderShipingData->order_status >= 2) {
            flash(translate('AWB already assigned.'))->error();
            $response['status'] = false;
            $response['message'] = "AWB already assigned.";
            return response()->json($response);
        }

        $courierId = !empty($orderInfo->courier_company_id) ? $orderInfo->courier_company_id : null;
        $shipmentId = !empty($orderShipingData->shipment_id) ? $orderShipingData->shipment_id : 0;

        $requestArr['shipment_id'] = $shipmentId;
        $requestArr['courier_id'] = $courierId;
        $url = $this->shipRocketBaseUrl . "courier/assign/awb";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);
        $orderPlaceResponse = json_decode($result, 1);
        if (!empty($orderPlaceResponse['status_code']) && $orderPlaceResponse['status_code'] == 400) {
            flash(translate('' . $orderPlaceResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPlaceResponse['message'];
            return response()->json($response);
        }

        if (!empty($orderPlaceResponse['status_code']) && $orderPlaceResponse['status_code'] == 422) {
            flash(translate('' . $orderPlaceResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPlaceResponse['message'];
            return response()->json($response);
        }

        if (!empty($orderPlaceResponse['status_code']) && $orderPlaceResponse['status_code'] == 350) {
            flash(translate('' . $orderPlaceResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPlaceResponse['message'];
            return response()->json($response);
        }

        $awbAssignStatus = !empty($orderPlaceResponse['awb_assign_status']) ? $orderPlaceResponse['awb_assign_status'] : null;
        if (empty($awbAssignStatus)) {
            flash(translate('AWB is not assigned'))->error();
            $response['status'] = false;
            $response['message'] = "AWB is not assigned";
            return response()->json($response);
        }

        $responseData = !empty($orderPlaceResponse['response']) ? $orderPlaceResponse['response'] : null;
        $awbCode = !empty($responseData['data']['awb_code']) ? $responseData['data']['awb_code'] : null;
        $orderShipingData->order_status = 2;
        $orderShipingData->awb_code = $awbCode;
        $orderShipingData->order_assign_awb = $result;
        if ($orderShipingData->save()) {
            $this->generateTrack($orderShipingData->id, $awbCode);
            $response['status'] = true;
            $response['message'] = "AWB assigned successfully.";
            flash(translate('AWB assigned successfully.'))->success();
            return response()->json($response);
        } else {
            flash(translate('Something went wrong while assigning awb.'))->error();
            $response['status'] = false;
            $response['message'] = "Something went wrong while assigning awb.";
            return response()->json($response);
        }

        echo $result;
        exit;
    }

    public function courierPickup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            flash(translate('Enter valid input'))->error();
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            return response()->json($response);
        }
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            flash(translate('Order not found'))->error();
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }
        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            flash(translate('Shipping order still not placed.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }

        if ($orderShipingData->order_status != 2) {
            flash(translate('Order already pickedup.'))->error();
            $response['status'] = false;
            $response['message'] = "Order already pickedup";
            return response()->json($response);
        }

        if ($orderShipingData->order_status >= 3) {
            flash(translate('Shipment already picked up.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipment already picked up.";
            return response()->json($response);
        }
        $shipmentId = !empty($orderShipingData->shipment_id) ? $orderShipingData->shipment_id : 0;
        if (empty($shipmentId)) {
            flash(translate('Shipment is not set yet'))->error();
            $response['status'] = false;
            $response['message'] = "Shipment is not set yet.";
            return response()->json($response);
        }
        $requestArr['shipment_id'] = $shipmentId;
        $url = $this->shipRocketBaseUrl . "courier/generate/pickup";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        // curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        // curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        $result = curl_exec($curl);
        curl_close($curl);
        $orderPickupResponse = json_decode($result, 1);
        if (!empty($orderPickupResponse['status_code']) && $orderPickupResponse['status_code'] == 400) {
            flash(translate('' . $orderPickupResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderPickupResponse['message'];
            return response()->json($response);
        }

        $pickupResponse = !empty($orderPickupResponse['response']) ? $orderPickupResponse['response'] : null;
        if (empty($pickupResponse)) {
            flash(translate('Pickup is not set'))->error();
            $response['status'] = false;
            $response['message'] = "Pickup is not set.";
            return response()->json($response);
        }
        $pickupScheduledDate = !empty($pickupResponse['pickup_scheduled_date']) ? $pickupResponse['pickup_scheduled_date'] : null;
        $pickupTokenNumber = !empty($pickupResponse['pickup_token_number']) ? $pickupResponse['pickup_token_number'] : null;

        $orderShipingData->order_status = 3;
        $orderShipingData->pickup_scheduled_date = $pickupScheduledDate;
        $orderShipingData->pickup_token_number = $pickupTokenNumber;
        $orderShipingData->pickup_response = $result;
        if ($orderShipingData->save()) {
            flash(translate('Pickup is set successfully.'))->success();
            $this->updateOrderStatus($orderId, 'on_delivery');
            $response['status'] = true;
            $response['message'] = "Pickup is set successfully.";
            return response()->json($response);
        } else {
            flash(translate('Something went wrong while setting pickup.'))->error();
            $response['status'] = false;
            $response['message'] = "Something went wrong while setting pickup.";
            return response()->json($response);
        }
        echo $result;
        exit;
    }

    public function generateManifest(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            flash(translate('Enter valid input'))->error();
            return response()->json($response);
        }
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            flash(translate('Order not found.'))->error();
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }
        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            flash(translate('Shipping order still not placed.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }

        if ($orderShipingData->order_status >= 4) {
            flash(translate('Shipment already generated manifest.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipment already generated manifest.";
            return response()->json($response);
        }
        $shipmentId = !empty($orderShipingData->shipment_id) ? $orderShipingData->shipment_id : 0;
        if (empty($shipmentId)) {
            flash(translate('Shipment is not set yet.'))->error();
            $response['status'] = false;
            $response['message'] = "Shipment is not set yet.";
            return response()->json($response);
        }

        $requestArr['shipment_id'] = $shipmentId;

        $url = $this->shipRocketBaseUrl . "manifests/generate";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);

        $orderGeneraetManifestResponse = json_decode($result, 1);
        if (!empty($orderGeneraetManifestResponse['status_code']) && $orderGeneraetManifestResponse['status_code'] == 400) {
            flash(translate('' . $orderGeneraetManifestResponse['message']))->error();
            $response['status'] = false;
            $response['message'] = $orderGeneraetManifestResponse['message'];
            return response()->json($response);
        }

        $manifestUrl = !empty($orderGeneraetManifestResponse['manifest_url']) ? $orderGeneraetManifestResponse['manifest_url'] : null;
        $orderShipingData->order_status = 4;
        $orderShipingData->manifest_url = $manifestUrl;
        $orderShipingData->generate_manifest_response = $result;
        if ($orderShipingData->save()) {
            $response['status'] = true;
            $response['message'] = "Manifest is generated successfully.";
            flash(translate('Manifest is generated successfully.'))->success();
            return response()->json($response);
        } else {
            $response['status'] = false;
            $response['message'] = "Something went wrong while setting generating manifest.";
            flash(translate('Something went wrong while setting generating manifest.'))->error();
            return response()->json($response);
        }
        echo $result;
        exit;
    }

    public function printManifest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            return response()->json($response);
        }
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }
        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }
        $manifestUrl = $orderShipingData->manifest_url;
        if (!empty($manifestUrl)) {
            $response['status'] = true;
            $response['message'] = "Manifest is generated successfully.";
            $response['manifest_url'] = $manifestUrl;
            return response()->json($response);
        }

        $orderId = $orderShipingData->shipping_order_id;
        if (empty($orderId)) {
            $response['status'] = false;
            $response['message'] = "Order is not availabe.";
            return response()->json($response);
        }
        $requestArr['order_ids'] = [$orderId];

        $url = $this->shipRocketBaseUrl . "manifests/print";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);

        $orderPrintManifestResponse = json_decode($result, 1);
        $manifestUrl = !empty($orderPrintManifestResponse['manifest_url']) ? $orderPrintManifestResponse['manifest_url'] : null;
        if (!empty($manifestUrl)) {
            $response['status'] = true;
            $response['message'] = "Manifest is generated successfully.";
            $response['manifest_url'] = $manifestUrl;
            return response()->json($response);
        } else {
            $response['status'] = false;
            $response['message'] = "No shipping order found.";
            return response()->json($response);
        }
        exit;
    }

    public function generateLabel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            return response()->json($response);
        }
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }
        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }

        $shipmentId = !empty($orderShipingData->shipment_id) ? $orderShipingData->shipment_id : 0;
        if (empty($shipmentId)) {
            $response['status'] = false;
            $response['message'] = "Shipment is not set yet.";
            return response()->json($response);
        }

        $labelUrl = !empty($orderShipingData->label_url) ? $orderShipingData->label_url : null;
        if (!empty($labelUrl)) {
            $response['status'] = true;
            $response['message'] = "Label is generated successfully.";
            $response['label_url'] = $labelUrl;
            return response()->json($response);
        }

        $requestArr['shipment_id'] = [$shipmentId];
        // $requestArr['shipment_id'] = !empty($request->shipment_id)?$request->shipment_id:[];

        $url = $this->shipRocketBaseUrl . "courier/generate/label";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);

        $orderGeneraetLabelResponse = json_decode($result, 1);
        if (empty($orderGeneraetLabelResponse['label_created']) && $orderGeneraetLabelResponse['label_created'] == 0) {
            $response['status'] = false;
            $response['message'] = $orderGeneraetLabelResponse['response'];
            return response()->json($response);
        }

        if (!empty($orderGeneraetLabelResponse['status_code']) && $orderGeneraetLabelResponse['status_code'] == 422) {
            $response['status'] = false;
            $response['message'] = $orderGeneraetLabelResponse['message'];
            return response()->json($response);
        }

        $labelUrl = !empty($orderGeneraetLabelResponse['label_url']) ? $orderGeneraetLabelResponse['label_url'] : null;
        $labelCreated = !empty($orderGeneraetLabelResponse['label_created']) ? $orderGeneraetLabelResponse['label_created'] : 0;
        $message = !empty($orderGeneraetLabelResponse['response']) ? $orderGeneraetLabelResponse['response'] : 0;
        $orderShipingData->label_created = $labelCreated;
        $orderShipingData->label_url = $labelUrl;
        if ($orderShipingData->save()) {
            $response['status'] = true;
            $response['message'] = "Label is generated successfully.";
            $response['label_url'] = $labelUrl;
            return response()->json($response);
        } else {
            $response['status'] = false;
            $response['message'] = "Something went wrong while generating label.";
            return response()->json($response);
        }
        echo $result;
        exit;
    }

    public function ordersPrintInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            $response['status'] = false;
            $response['message'] = "Enter valid input";
            return response()->json($response);
        }
        $orderId = !empty($request->order_id) ? decrypt($request->order_id) : 0;
        $orderInfo = Order::findOrFail($orderId);
        if (empty($orderInfo)) {
            return response()->json([
                "status" => false,
                "message" => "Order not found",
            ]);
        }
        $orderShipingData = OrderShippingData::where('original_order_id', $orderId)
            ->first();
        if (empty($orderShipingData)) {
            $response['status'] = false;
            $response['message'] = "Shipping order still not placed.";
            return response()->json($response);
        }

        $orderId = $orderShipingData->shipping_order_id;
        if (empty($orderId)) {
            $response['status'] = false;
            $response['message'] = "Order is not availabe.";
            return response()->json($response);
        }
        $requestArr['ids'] = [$orderId];

        $url = $this->shipRocketBaseUrl . "/orders/print/invoice";
        $requestData = json_encode($requestArr);
        $header = [];
        $header[] = 'Content-length: ' . strlen($requestData);
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);
        $orderGeneraetInvoicelResponse = json_decode($result, 1);
        if (empty($orderGeneraetInvoicelResponse['is_invoice_created'])) {
            $response['status'] = false;
            $response['message'] = $orderGeneraetInvoicelResponse['message'];
            return response()->json($response);
        }

        if (!empty($orderGeneraetInvoicelResponse['status_code']) && $orderGeneraetInvoicelResponse['status_code'] == 500) {
            $response['status'] = false;
            $response['message'] = $orderGeneraetInvoicelResponse['message'];
            return response()->json($response);
        }

        $invoiceUrl = !empty($orderGeneraetInvoicelResponse['invoice_url']) ? $orderGeneraetInvoicelResponse['invoice_url'] : null;
        $isInvoiceCreated = !empty($orderGeneraetInvoicelResponse['is_invoice_created']) ? $orderGeneraetInvoicelResponse['is_invoice_created'] : 0;
        $orderShipingData->is_invoice_created = $isInvoiceCreated;
        $orderShipingData->invoice_url = $invoiceUrl;
        if ($orderShipingData->save()) {
            $response['status'] = true;
            $response['message'] = "Invoice is generated successfully.";
            $response['invoice_url'] = $invoiceUrl;
            return response()->json($response);
        } else {
            $response['status'] = false;
            $response['message'] = "Something went wrong while generating invoice.";
            return response()->json($response);
        }
        echo $result;
        exit;

    }

    private function generateTrack($orderShippingId = 0, $awbCode = "")
    {
        $orderShipingData = OrderShippingData::find($orderShippingId);
        if (empty($orderShipingData)) {
            return false;
        }

        $url = $this->shipRocketBaseUrl . "courier/track/awb/$awbCode";
        $header = [];
        $header[] = 'Content-type: application/json';
        $header[] = 'Authorization: bearer ' . $this->shipRocketToken;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl);
        curl_close($curl);
        $orderTrackResponse = json_decode($result, 1);
        $trackUrl = $orderTrackResponse['tracking_data']['track_url'];
        $orderShipingData->tracking_response = $result;
        $orderShipingData->tracking_url = $trackUrl;
        $orderShipingData->save();
        return true;
    }

    private function updateOrderStatus($orderId = 0, $orderStatus = "")
    {
        if (empty($orderStatus)) {
            return false;
        }
        $order = Order::findOrFail($orderId);
        foreach ($order->orderDetails as $key => $orderDetail) {
            $orderDetail->delivery_status = $orderStatus;
            $orderDetail->save();
        }
        return true;
    }
}
