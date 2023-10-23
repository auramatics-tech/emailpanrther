<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('campaign-template/{uid}/add_step', 'CampaignTemplateController@add_step');
Route::post('campaign-template/{uid}/delete_step', 'CampaignTemplateController@delete_step');
Route::post('campaign-template/{uid}/update_subject', 'CampaignTemplateController@update_subject');
Route::post('campaign-template/{uid}/update_content', 'CampaignTemplateController@update_content');
Route::post('campaign-template/{uid}/wait_for', 'CampaignTemplateController@wait_for');
Route::post('campaign-template/{uid}/save_settings', 'CampaignTemplateController@save_settings');
Route::get('campaign-template/{uid}/get_settings', 'CampaignTemplateController@campaign_step_settings');
Route::post('campaign-template/{uid}/update_template', 'CampaignTemplateController@update_template');
Route::post('campaign-template/{uid}/remove_settings', 'CampaignTemplateController@remove_condition');
Route::post('campaign-template/{uid}/add_variant', 'CampaignTemplateController@add_variant');
Route::post('campaign-template/{uid}/delete_variant', 'CampaignTemplateController@delete_variant');
Route::post('campaign-template/{uid}/update_variant_status', 'CampaignTemplateController@update_variant_status');
Route::match(['get','post'],'campaign-template/{id}','CampaignTemplateController@campaign_template');

Route::group(['namespace' => 'Api', 'prefix' => 'v1', 'middleware' => 'auth:api'], function () {
    //
    Route::get('', function () {
        return \Response::json(\Auth::guard('api')->user());
    });

    // Simple authentication
    Route::get('me', function () {
        return \Response::json(\Auth::guard('api')->user());
    });

    // List
    Route::delete('lists/{uid}', 'MailListController@delete');
    Route::post('lists/{uid}/add-field', 'MailListController@addField');
    Route::resource('lists', 'MailListController');

    // Campaign
    Route::post('campaigns/{uid}/pause', 'CampaignController@pause');
    Route::get('c/get-all', 'CampaignController@index');
    Route::get('campaigns/{uid}/open-clicked', 'CampaignController@opens_clicked');
    Route::get('campaigns/{uid}/open-clicked-all', 'CampaignController@opens_clicked_all');
    Route::get('digitalocean', 'CampaignController@digitalocean_clean_up');
    Route::post('transform-tag', 'CampaignController@get_tag_value');
    Route::resource('campaigns', 'CampaignController');

    // Subscriber
    Route::post('subscribers/{uid}/add-tag', 'SubscriberController@addTag');
    Route::get('subscribers/email/{email}', 'SubscriberController@showByEmail');
    Route::patch('lists/{list_uid}/subscribers/{uid}/subscribe', 'SubscriberController@subscribe');
    Route::patch('lists/{list_uid}/subscribers/{uid}/unsubscribe', 'SubscriberController@unsubscribe');
    Route::delete('subscribers/{uid}', 'SubscriberController@delete');

    Route::resource('subscribers', 'SubscriberController');

    // Automation
    Route::post('automations/{uid}/api/call', 'AutomationController@apiCall');

    // Sending server
    Route::resource('sending_servers', 'SendingServerController');

    // Plan
    Route::resource('plans', 'PlanController');

    // Customer
    Route::match(['get','post'], 'login-token', 'CustomerController@loginToken');
    Route::post('customers/{uid}/assign-plan/{plan_uid}', 'CustomerController@assignPlan');
    Route::patch('customers/{uid}/disable', 'CustomerController@disable');
    Route::patch('customers/{uid}/enable', 'CustomerController@enable');
    Route::resource('customers', 'CustomerController');

    // Subscription
    Route::resource('subscriptions', 'SubscriptionController');

    // File
    Route::post('file/upload', 'FileController@upload');

    // File
    Route::post('automations/{uid}/execute', 'AutomationController@execute')->name('automation_execute');

    // Subscription
    Route::resource('notification', 'NotificationController');


    Route::post('dashboard/campaign-count', 'DashboardDataController@getcounts');
    Route::post('dashboard/emailsent', 'DashboardDataController@emailsent');
    Route::post('dashboard/emailsopened', 'DashboardDataController@emailsopened');
    Route::post('dashboard/emailsclicked', 'DashboardDataController@emailsclicked');
    Route::post('dashboard/campaign-date-count', 'DashboardDataController@datecount');
    Route::resource('dashboarddata', 'DashboardDataController');
});
