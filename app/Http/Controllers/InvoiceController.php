<?php

namespace App\Http\Controllers;

use App\Models\Detail_invoice;
use App\Models\Detail_invoice_noacc;
use App\Models\Invoice;
use App\Models\Invoice_noacc;
use App\Models\Status;
use App\Models\Status_noacc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Cart;
use Illuminate\Support\Facades\Session;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('client.cart');
    }
    public function index0()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.canceledorder',[
            'currentURL'=> $currentURL,
        ]);
    }
    public function index1()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.noprocessorder',[
            'currentURL'=> $currentURL,
        ]);
    }
    public function index2()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.confirmedorder',[
            'currentURL'=> $currentURL,
        ]);
    }
    public function index3()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.packingorder',[
            'currentURL'=> $currentURL,
        ]);
    }
    public function index4()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.deliveryorder',[
            'currentURL'=> $currentURL,
        ]);
    }
    public function index5()
    {
        $currentURL = \Route::current()->uri();
        return view('admin.order.successfulorder',[
            'currentURL'=> $currentURL,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function execPostRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch);
        //close connection
        curl_close($ch);
        return $result;
    }

    public function checkInvoice($prd_id){
        $product = DB::table('detail_invoice')
            ->join('invoice', 'detail_invoice.invoice_id','=', 'invoice.invoice_id')
            ->join('status', 'invoice.invoice_id','=', 'status.invoice_id')
            ->select('detail_invoice.*','status.status')
            ->where('detail_invoice.itemsid','=', $prd_id)
            ->get();

        $totalamount = 0;
        foreach ($product as $c){
            $totalamount += $c->amount;
        }

        return $totalamount;
    }

    public function checkBatch($prd_id,$batch){
        $prdbatch = DB::table('batch_price')
            ->where('prdid','=',$prd_id)
            ->where('batch','=',$batch)
            ->get();

        $totalamount = $prdbatch[0]->batch_amount;
        return $totalamount;
    }

    public function test(Request $request){
//        dd($request);
//        if ($request->resultCode == 0)
        if ($request->resultCode != null){
            if (Auth::guard("customer")->check()){
                $userId = Auth::guard("customer")->id();
                Cart::session($userId);
                $total = Cart::getTotal();
                if (Cart::isEmpty()){
                    return redirect('cart');
                }else{
                    $cartin = Cart::getContent()->toArray();
                    $flagcountcheck = false;
                    foreach ($cartin as $c){
                        $detail = DB::table('properties')
                            ->where('itemsid','=',$c['id'])
                            ->where('size','=',$c['attributes'][0]['size'])
                            ->where('color','=',$c['attributes'][0]['color'])
                            ->get();

                        $totalamount = 0;
                        foreach ($detail as $d){
                            $totalamount += $d->amount;
                        }
                        if ($c['quantity']>$totalamount){
                            $flagcountcheck = true;
                        }
                    }
                    if ($flagcountcheck){
                        return redirect('fail');
                    }
                    $items = Invoice::create([
                        'cusid' => $userId,
                        'pay' => $total,
                        'payment' => 'momo',
                        'delivery' => session('delivery')
                    ]);
                    $idinvoice = DB::table('invoice')->latest('created_at')->first();
                    $status = Status::create([
                        'invoice_id'=> $idinvoice->invoice_id,
                        'status'=> 1,
                    ]);

                    foreach ($cartin as $c){
                        $prdbatch = DB::table('batch_price')
                            ->where('prdid','=',$c['id'])
                            ->get();
                        $length = count($prdbatch);
                        $check = 0;
                        $start;
                        $end;
                        $input1;
                        for ($i=1;$i<=$length;$i++){
                            $check += $this->checkBatch($c['id'],$i);
                            if ($this->checkInvoice($c['id'])<$check){
                                $start = $i;
                                $input1 = $check - $this->checkInvoice($c['id']);
                            }
                            if (($this->checkInvoice($c['id'])+$c['quantity'])<=$check){
                                $end = $i;
                                if ($start == $end){
                                    $batch1 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$start)
                                        ->get();

                                    $cost1 = $batch1['0']->cost;
                                    $detail1 = Detail_invoice::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $c['quantity'],
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost1,
                                    ]);
                                    $change = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $start )
                                        ->decrement('amount', $c['quantity']);
                                }else{
                                    $input2 = $c['quantity'] - $input1;
                                    $batch1 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$start)
                                        ->get();
                                    $cost1 = $batch1['0']->cost;
                                    $batch2 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$end)
                                        ->get();
                                    $cost2 = $batch2['0']->cost;
                                    $detail1 = Detail_invoice::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $input1,
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost1,
                                    ]);
                                    $detail2 = Detail_invoice::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $input2,
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost2,
                                    ]);
                                    $change1 = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $start )
                                        ->decrement('amount', $input1);
                                    $change2 = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $end )
                                        ->decrement('amount', $input2);
                                }
                                Cart::clear();
                                break;
                            }
                        }
                    }
                    return redirect('success');
                }
            }else{
                $userId = Session::getId();
                Cart::session($userId);
                $total = Cart::getTotal();
                if (Cart::isEmpty()){
                    return redirect('cart');
                }else{
                    $cartin = Cart::getContent()->toArray();
                    $flagcountcheck = false;
                    foreach ($cartin as $c){
                        $detail = DB::table('properties')
                            ->where('itemsid','=',$c['id'])
                            ->where('size','=',$c['attributes'][0]['size'])
                            ->where('color','=',$c['attributes'][0]['color'])
                            ->get();

                        $totalamount = 0;
                        foreach ($detail as $d){
                            $totalamount += $d->amount;
                        }
                        if ($c['quantity']>$totalamount){
                            $flagcountcheck = true;
                        }
                    }
                    if ($flagcountcheck){
                        return redirect('fail');
                    }
                    $usernoacc = DB::table('customer_noacc')
                        ->where('sessionid','=',$userId)
                        ->latest()
                        ->get();
                    $items = Invoice_noacc::create([
                        'cusid' => $usernoacc[0]->cus_id,
                        'pay' => $total,
                        'payment' => 'momo',
                        'delivery' => session('delivery')
                    ]);
                    $idinvoice = DB::table('invoice_noacc')->latest('created_at')->first();
                    $status = Status_noacc::create([
                        'invoice_id'=> $idinvoice->invoice_id,
                        'status'=> 1,
                    ]);

                    foreach ($cartin as $c){
                        $prdbatch = DB::table('batch_price')
                            ->where('prdid','=',$c['id'])
                            ->get();
                        $length = count($prdbatch);
                        $check = 0;
                        $start;
                        $end;
                        $input1;
                        for ($i=1;$i<=$length;$i++){
                            $check += $this->checkBatch($c['id'],$i);
                            if ($this->checkInvoice($c['id'])<$check){
                                $start = $i;
                                $input1 = $check - $this->checkInvoice($c['id']);
                            }
                            if (($this->checkInvoice($c['id'])+$c['quantity'])<=$check){
                                $end = $i;
                                if ($start == $end){
                                    $batch1 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$start)
                                        ->get();

                                    $cost1 = $batch1['0']->cost;
                                    $detail1 = Detail_invoice_noacc::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $c['quantity'],
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost1,
                                    ]);
                                    $change = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $start )
                                        ->decrement('amount', $c['quantity']);
                                }else{
                                    $input2 = $c['quantity'] - $input1;
                                    $batch1 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$start)
                                        ->get();
                                    $cost1 = $batch1['0']->cost;
                                    $batch2 = DB::table('batch_price')
                                        ->where('prdid','=',$c['id'])
                                        ->where('batch','=',$end)
                                        ->get();
                                    $cost2 = $batch2['0']->cost;
                                    $detail1 = Detail_invoice_noacc::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $input1,
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost1,
                                    ]);
                                    $detail2 = Detail_invoice_noacc::create([
                                        'itemsid'=> $c['id'],
                                        'invoice_id'=> $idinvoice->invoice_id,
                                        'size'=> $c['attributes'][0]['size'],
                                        'color'=> $c['attributes'][0]['color'],
                                        'amount'=> $input2,
                                        'price_one'=> $c['price'],
                                        'cost_one'=> $cost2,
                                    ]);
                                    $change1 = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $start )
                                        ->decrement('amount', $input1);
                                    $change2 = DB::table('properties')
                                        ->where('size','=', $c['attributes'][0]['size'])
                                        ->where('color','=', $c['attributes'][0]['color'])
                                        ->where('batch','=', $end )
                                        ->decrement('amount', $input2);
                                }
                                Cart::clear();
                                break;
                            }
                        }

                    }
                    return redirect('success');
                }
            }
        }
        return redirect()->to('cart');
    }

    public function store(Request $request)
    {
        session(['delivery' => $request->delivery]);

        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";


        $partnerCode = 'MOMOBKUN20180529';
        $accessKey = 'klm05TvNBzhg7h7j';
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderInfo = "Thanh toán qua MoMo";
        $amount = $request->get('amount')*23000;
        $orderId = time() ."";
        $redirectUrl = "http://127.0.0.1:8000/test";
        $ipnUrl = "http://127.0.0.1:8000/test";
        $extraData = "";



        $requestId = time() . "";
        $requestType = "payWithATM";
//        $extraData = ($_POST["extraData"] ? $_POST["extraData"] : "");
        //before sign HMAC SHA256 signature
        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $secretKey);
        $data = array('partnerCode' => $partnerCode,
            'partnerName' => "Test",
            "storeId" => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature);
        $result = $this->execPostRequest($endpoint, json_encode($data));
        $jsonResult = json_decode($result, true);  // decode json

        //Just a example, please check more in there
        return redirect()->to($jsonResult['payUrl']);
//        header('Location: ' . $jsonResult['payUrl']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id,$type)
    {
        return view('admin.order.order',[
            'id' => $id,
            'type' => $type
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
