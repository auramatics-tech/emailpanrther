<?php

namespace Acelle\Http\Controllers;

use Acelle\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Acelle\Model\ServerLog;
use DB;

class ServerLogsController extends Controller
{
    //
    public function index(Request $request)
    {
        $items = ServerLog::get();

        return view('servers_logs.index', [
            'items' => $items,
        ]);
    }
    public function listing(Request $request)
    {
        $items = ServerLog::when($request->keyword, function ($query) use ($request) {
            $query->where('title','like', "%".$request->keyword."%");
        })->paginate($request->per_page);

        return view('servers_logs._list', [
            'items' => $items,
        ]);
    }
    public function store(Request $request)
    {
        $validator =  $request->validate([
            'title' => 'required',
            'response' => 'required',
        ]);
        if (isset($request->id)) {
            $log = ServerLog::find($request->id);
        } else {
            $log = new ServerLog;
        }
        $log->title = $request->title;
        $log->response = $request->response;
        $log->save();
        $request->session()->flash('alert-success', 'Log updated');
        return redirect()->action('ServerLogsController@index');
    }
    public function edit($id)
    {
        $item = ServerLog::Find($id);
        return view('servers_logs.create', [
            'item' => $item,
        ]);
    }
    public function delete(Request $request, $id)
    {
        $log = ServerLog::Find($id)->delete();
        $request->session()->flash('alert-success', 'Server log deleted successfully');
        return back()->with('success', 'Server log deleted successfully');
    }
}
