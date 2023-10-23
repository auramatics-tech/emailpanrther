<?php

namespace Acelle\Http\Controllers;

use Acelle\Http\Controllers\Controller;
use Acelle\Model\Proxies;
use Illuminate\Http\Request;

class ProxiesController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user();

        return view('proxies.index', [
            'items' => $items,
        ]);
    }

    public function listing(Request $request)
    {

        $items =  Proxies::orderby('id', 'desc')->paginate(10);

        return view('proxies._list', [
            'items' => $items,
        ]);
    }

    public function create(Request $request)
    {

        return view('proxies.create');
    }
    public function edit(Request $request)
    {
      $item = Proxies::find($request->proxy);
        return view('proxies.create',compact('item'));
    }
       public function store(Request $request)
    {
        // New sending server
        // list($validator, $assignment) = DomainAssignment::createFromArray(array_merge($request->all(), [
        //     'customer_id' => $request->user()->customer->id,
        // ]));

        // // Failed
        // if ($validator->fails()) {
        //     if ($assignment->isExtended()) {
        //         // Redirect to plugin's create page
        //         return redirect()->back()->withErrors($validator)->withInput();
        //     } else {
        //         return redirect()->action('DomainAssignmentController@create')
        //             ->withErrors($validator)->withInput();
        //     }
        // }
          $validator =  $request->validate([
            'ip_address'=>'required',
            'port'=>'required',
          ]);
          if (isset($request->proxy)) {
            $proxies = Proxies::find($request->proxy);
          }else{
            $proxies = new Proxies;
          }

          
          $proxies->ip_address=$request->ip_address;
          $proxies->port=$request->port;
          
          if ($proxies->save()) {
            $request->session()->flash('alert-success', 'Proxy created');
            return redirect()->action('ProxiesController@index');
          } else {
            $request->session()->flash('alert-danger', 'Something Went Wrong');
            return redirect()->back();
          }

        // Success
        
    }

    public function destroy(Request $request)
    {
        // echo 'here'; die;
        $items = Proxies::where(
            'id', $request->proxy)->delete();
               // authorize
            // if ($request->user()->customer->can('delete', $items)) {
            //     $items->doDelete();
            // }
            // $request->session()->flash('alert-success', 'Proxy deleted successfully');
            return back()->with('success', 'Proxy deleted successfully');
       
    }

}
