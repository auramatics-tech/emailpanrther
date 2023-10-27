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
    return view('proxies.create', compact('item'));
  }
  public function store(Request $request)
  {
    $validator =  $request->validate([
      'ip_address.0' => 'required',
      'port.0' => 'required',
    ]);
    if (isset($request->ip_address) && count($request->ip_address)) {
      foreach ($request->ip_address as $key => $ip_address) {
        if (isset($request->proxy[$key])) {
          $proxies = Proxies::find($request->proxy[$key]);
        } else {
          $proxies = new Proxies;
        }
        $proxies->ip_address = $request->ip_address[$key];
        $proxies->port = $request->port[$key];
        $proxies->save();
      }
    }
    $request->session()->flash('alert-success', 'Proxy created');
    return redirect()->action('ProxiesController@index');
    // Success

  }

  public function destroy(Request $request)
  {
    // echo 'here'; die;
    $items = Proxies::where(
      'id',
      $request->proxy
    )->delete();
    // authorize
    // if ($request->user()->customer->can('delete', $items)) {
    //     $items->doDelete();
    // }
    // $request->session()->flash('alert-success', 'Proxy deleted successfully');
    return back()->with('success', 'Proxy deleted successfully');
  }
}
