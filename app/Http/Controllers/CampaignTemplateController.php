<?php

namespace Acelle\Http\Controllers;

use Illuminate\Http\Request;
use Acelle\Model\Template;
use Acelle\Model\Setting;
use App;
use File;
use Acelle\Library\Tool;
use Acelle\Model\Campaign;
use Acelle\Model\CampaignSteps;
use Acelle\Model\CampaignStepsTemp;
use Acelle\Model\CampaignStepsSettings;
use Acelle\Model\CampaignStepsVariant;
use Acelle\Model\CampaignStepsVariantTemp;
use Acelle\Model\CampaignStepsSettingsTemp;
use Illuminate\Support\Facades\Crypt;
use Schema;
use DB;


class CampaignTemplateController extends Controller
{

    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->header('X-Frame-Options', 'ALLOW-FROM https://dashboard.emailpanther.com/');
        return $response;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function campaign_template(Request $request)
    {
        $campaign_id = base64_decode($request->id);

        $campaign = Campaign::findByUid($campaign_id);
        if (!isset($campaign->id))
            return redirect('/');

        $campaign_id = $campaign->id;

        // Delete data from temp and load again; this needs to be changed after for approval and disapproval
        Schema::disableForeignKeyConstraints();

        // Delete existing campaign steps, variants, and settings in bulk
        CampaignStepsVariantTemp::whereIn('campaign_steps_id', function ($query) use ($campaign_id) {
            $query->select('id')
                ->from('campaign_steps_temp')
                ->where('campaign_id', $campaign_id);
        })->delete();

        CampaignStepsSettingsTemp::whereIn('campaign_step_id', function ($query) use ($campaign_id) {
            $query->select('id')
                ->from('campaign_steps_temp')
                ->where('campaign_id', $campaign_id);
        })->delete();

        CampaignStepsTemp::where('campaign_id', $campaign_id)->delete();

        Schema::enableForeignKeyConstraints();

        // Copy data from main table to temp tables in bulk
        $campaign_steps = CampaignSteps::where('campaign_id', $campaign_id)->get()->toArray();
        if (count($campaign_steps)) {
            $campaign_steps_temp_data = [];
            $campaign_steps_variant_temp_data = [];
            $campaign_steps_settings_temp_data = [];

            foreach ($campaign_steps as $campaign_step) {
                $campaign_step_id = $campaign_step['id'];
                unset($campaign_step['id']);
                unset($campaign_step['created_at']);
                unset($campaign_step['updated_at']);

                $step_id = DB::table('campaign_steps_temp')->insertGetId($campaign_step);
                $campaign_step_temp_data[] = [
                    'campaign_id' => $campaign_id,
                    'main_campaign_step_id' => $campaign_step_id,
                    'temp_campaign_step_id' => $step_id,
                ];

                $variants = CampaignStepsVariant::where('campaign_steps_id', $campaign_step_id)->get()->toArray();
                foreach ($variants as $variant) {
                    $main_variant_id = $variant['id'];
                    unset($variant['id']);
                    unset($variant['created_at']);
                    unset($variant['updated_at']);
                    $variant['campaign_steps_id'] = $step_id;
                    $campaign_steps_variant_temp_data[] = $variant;
                }

                $settings = CampaignStepsSettings::where('campaign_step_id', $campaign_step_id)->get()->toArray();
                foreach ($settings as $setting) {
                    $main_setting_id = $setting['id'];
                    unset($setting['id']);
                    unset($setting['created_at']);
                    unset($setting['updated_at']);
                    $setting['campaign_step_id'] = $step_id;
                    $campaign_steps_settings_temp_data[] = $setting;
                }
            }

            // Bulk insert campaign steps, variants, and settings
            CampaignStepsTemp::insert($campaign_steps_temp_data);
            CampaignStepsVariantTemp::insert($campaign_steps_variant_temp_data);
            CampaignStepsSettingsTemp::insert($campaign_steps_settings_temp_data);
        }

        $campaign_step = CampaignStepsTemp::where('campaign_id', $campaign_id)->get();

        return view('templates.campaign_template', compact('campaign', 'campaign_step'));
    }

    public function add_step(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);

        list($CampaignSteps, $key) = $this->new_step($campaign, $request);

        $step = view('campaigns.template.public.steps', ['campaign' => $campaign, 'step' => $CampaignSteps, 'key' => $key])->render();

        return response()->json(['html' => $step]);
    }

    protected function new_step($campaign, $request)
    {

        $campaign_step_1 = CampaignStepsTemp::where(['campaign_id' => $campaign->id, 'step_number' => 1])->first();
        if (isset($campaign_step_1->id)) {
            $campaign_variant_1 = CampaignStepsVariantTemp::where(['campaign_steps_id' => $campaign_step_1->id, 'variant' => 1])->first();
        }

        $CampaignSteps = new CampaignStepsTemp;
        $CampaignSteps->campaign_id = $campaign->id;
        $CampaignSteps->step_number = $request->step_number;
        $CampaignSteps->next_step_wait_time  = 7;
        $CampaignSteps->save();

        $CampaignStepsVariant = new CampaignStepsVariantTemp;
        $CampaignStepsVariant->campaign_steps_id = $CampaignSteps->id;
        $CampaignStepsVariant->variant = 1;
        $CampaignStepsVariant->subject = 'Re: ' . (isset($campaign_variant_1->id) ? $campaign_variant_1->subject : '');
        $CampaignStepsVariant->content = '<p></p>
        <p> Not interested? <br><a href ="{UNSUBSCRIBE_URL}">Unsubscribe here</a></p><p><strong>
        <div style="margin-left: 40px;">
        From: </strong>{FROM_NAME} {FROM_EMAIL}<br><strong>Sent: </strong>{FROM_SENT}<br><strong>To</strong>: {SUBSCRIBER_EMAIL}<br><strong>Subject:&nbsp;</strong>' . (isset($campaign_variant_1->subject) ? $campaign_variant_1->subject : '') . '</p>' . (isset($campaign_variant_1->content) ? $campaign_variant_1->content : '') . '</div>';
        $CampaignStepsVariant->save();

        $key = count($campaign->steps) - 1;

        $campaign->fresh();

        return [$CampaignSteps, $key];
    }

    public function delete_step(Request $request)
    {
        $campaign = Campaign::findByUid($request->uid);
        $CampaignSteps = CampaignStepsTemp::find($request->id);

        $CampaignStepsSettings = CampaignStepsSettingsTemp::where('campaign_step_id', $request->id)->delete();
        $CampaignStepsVariant = CampaignStepsVariantTemp::where('campaign_steps_id', $request->id)->delete();
        if (isset($CampaignSteps->id))
            $CampaignSteps->delete();
        $campaign->fresh();

        $steps = CampaignStepsTemp::where('campaign_id', $campaign->id)->get();
        if (count($steps)) {
            foreach ($steps as $key => $step) {
                $step_number = $key + 1;
                CampaignStepsTemp::where('campaign_id', $step->campaign_id)
                    ->where('step_number', $step->step_number)
                    ->update(['step_number' => $step_number]);
            }
        }


        return response()->json(['status' => 1]);
    }

    public function update_subject(Request $request)
    {
        $CampaignStepsVariant = CampaignStepsVariantTemp::find($request->variant);
        $subject = $request->subject;
        if (isset($CampaignStepsVariant->id)) {
            $camp_step = CampaignStepsTemp::find($CampaignStepsVariant->campaign_steps_id);
            if (isset($camp_step->id)) {
                $campaign = Campaign::find($camp_step->campaign_id);
                $tags = Template::tags($campaign->defaultMailList);
                $tags[]['name'] = 'FROM_NAME';
                $tags[]['name'] = 'FROM_EMAIL';
                $tags[]['name'] = 'FROM_SENT';
                if (count($tags)) {
                    foreach ($tags as $tag) {
                        $tag_name = $tag["name"];
                        $content_tag = str_replace('_', ' ', str_replace('SUBSCRIBER_', '', $tag["name"]));
                        if ($content_tag == 'EMAIL') {
                            $subject = str_replace("{EMAIL}", "{SUBSCRIBER_EMAIL}", $subject);
                        } else
                            $subject = str_replace("{$content_tag}", "{$tag_name}", $subject);
                    }
                }


                $subject = str_replace("{UNSUBSCRIBE URL}", "{UNSUBSCRIBE_URL}", $subject);
                $CampaignStepsVariant->subject = $subject;
                $CampaignStepsVariant->save();
            }
        }
        return response()->json(['status' => 1]);
    }

    public function update_content(Request $request)
    {
        $CampaignStepsVariant = CampaignStepsVariantTemp::find($request->variant);
        $content = $request->content;
        if (isset($CampaignStepsVariant->id)) {
            $camp_step = CampaignStepsTemp::find($CampaignStepsVariant->campaign_steps_id);
            if (isset($camp_step->id)) {
                $campaign = Campaign::find($camp_step->campaign_id);

                $tags = Template::tags($campaign->defaultMailList);
                $tags[]['name'] = 'FROM_NAME';
                $tags[]['name'] = 'FROM_EMAIL';
                $tags[]['name'] = 'FROM_SENT';
                if (count($tags)) {
                    foreach ($tags as $tag) {
                        $tag_name = $tag["name"];
                        $content_tag = str_replace('_', ' ', str_replace('SUBSCRIBER_', '', $tag["name"]));
                        if ($content_tag == 'EMAIL') {
                            $content = str_replace("{EMAIL}", "{SUBSCRIBER_EMAIL}", $content);
                        } else
                            $content = str_replace("{$content_tag}", "{$tag_name}", $content);
                    }
                }

                $content = str_replace("{UNSUBSCRIBE URL}", "{UNSUBSCRIBE_URL}", $content);
                $CampaignStepsVariant->content = $content;
                $CampaignStepsVariant->save();
            }
        }
        return response()->json(['status' => 1]);
    }

    public function wait_for(Request $request)
    {
        $CampaignSteps = CampaignStepsTemp::find($request->step_number);
        $CampaignSteps->next_step_wait_time = $request->next_step_wait_time;
        $CampaignSteps->save();
        return response()->json(['status' => 1]);
    }

    public function add_variant(Request $request)
    {
        $last_variant = CampaignStepsVariantTemp::where('campaign_steps_id', $request->step)->orderby('id', 'desc')->first();

        $CampaignSteps = CampaignStepsTemp::find($request->step);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $linked_campaigns = json_decode($campaign->linked_campaigns);

                $last_variant = CampaignStepsVariantTemp::where('campaign_steps_id', $CampaignSteps->id)->orderby('id', 'desc')->first();

                $CampaignStepsVariant = new CampaignStepsVariantTemp;
                $CampaignStepsVariant->campaign_steps_id = $CampaignSteps->id;
                $CampaignStepsVariant->variant = $last_variant->variant + 1;
                $CampaignStepsVariant->subject = '';
                $CampaignStepsVariant->content = '<p></p>
                        <p> Not interested? <br><a href ="{UNSUBSCRIBE_URL}">Unsubscribe here</a></p>';
                $CampaignStepsVariant->save();
            }
        }

        $all_variants = CampaignStepsVariantTemp::where('campaign_steps_id', $request->step)->orderby('id', 'asc')->get();
        $step = CampaignStepsTemp::find($request->step);

        $variants = view('campaigns.template.variants', compact('step'))->render();

        return response()->json(['variants' => $variants]);
    }

    public function update_variant_status(Request $request)
    {
        $variant = CampaignStepsVariantTemp::where('id', $request->variant)->first();
        $CampaignSteps = CampaignStepsTemp::find($variant->campaign_steps_id);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {
                $variant->status = $request->status;
                $variant->save();
            }
        }


        return response()->json(['variant' => $variant]);
    }

    public function save_settings(Request $request)
    {
        $step = CampaignStepsTemp::find($request->step_id);
        if (isset($step->id)) {
            $campaign = Campaign::find($step->campaign_id);
            if (isset($campaign->id)) {
                $step->next_step_wait_time = $request->next_step_wait_time;

                CampaignStepsSettingsTemp::where('campaign_step_id', $step->id)->delete();

                if (count($request->condition)) {
                    foreach ($request->condition as $key => $condition) {
                        $settings = new CampaignStepsSettingsTemp();
                        $settings->campaign_step_id = $step->id;
                        $settings->condition = $condition;
                        $settings->condition_purpose = $request->condition_value[$key];
                        $settings->wait_time = $request->wait_time[$key];
                        $settings->save();
                    }
                }
            }
        }

        return response()->json(['status' => 1]);
    }

    public function campaign_step_settings(Request $request)
    {
        $conditions = CampaignStepsSettingsTemp::where('campaign_step_id', $request->step_id)->get();

        $conditions_html = view('campaigns.template._settings', compact('conditions'))->render();

        return response()->json(['conditions' => $conditions_html, 'count' => count($conditions)]);
    }

    public function remove_condition(Request $request)
    {
        $condition = CampaignStepsSettingsTemp::find($request->id);
        $step = CampaignStepsTemp::find($condition->campaign_step_id);
        $condition = CampaignStepsSettingsTemp::where('id', $request->id)->delete();

        return response()->json(['status' => 1, 'message' => 'Condition deleted successfully']);
    }


    public function delete_variant(Request $request)
    {
        $variant = CampaignStepsVariantTemp::find($request->variant);

        $CampaignSteps = CampaignStepsTemp::find($variant->campaign_steps_id);
        if (isset($CampaignSteps->id)) {
            $campaign = Campaign::find($CampaignSteps->campaign_id);
            if (isset($campaign->id)) {

                CampaignStepsVariantTemp::where('campaign_steps_id', $CampaignSteps->id)
                    ->where('variant', $variant->variant)
                    ->delete();

                $records = CampaignStepsVariantTemp::where('campaign_steps_id', $CampaignSteps->id)->orderby('id', 'asc')->get();

                if (count($records)) {
                    foreach ($records as $key => $row) {
                        $row->variant = ++$key;
                        $row->save();
                    }
                }
            }
        }


        $all_variants = CampaignStepsVariantTemp::where('campaign_steps_id', $request->step)->orderby('id', 'asc')->get();
        $step = CampaignStepsTemp::find($request->step);

        $variants = view('campaigns.template.public.variants', compact('step'))->render();

        return response()->json(['variants' => $variants]);
    }

    public function update_template($uid, Request $request)
    {
        try {
            $main_campaign = Campaign::findByUid($uid);
            $is_paused = $main_campaign->isPaused();

            DB::beginTransaction();
            Schema::disableForeignKeyConstraints();
            if (!isset($main_campaign->id))
                return response()->json(['status' => 0, 'message' => 'Campaign not found']);

            $linked_campaigns = json_decode($main_campaign->linked_campaigns);
            if (count($linked_campaigns)) {
                foreach ($linked_campaigns as $l_camp) {

                    $campaign = Campaign::find($l_camp);
                    if (isset($campaign->id)) {
                        // $campaign->pause();


                        $campaign_steps_old = CampaignSteps::where('campaign_id', $campaign->id)->get();
                        if (count($campaign_steps_old)) {
                            foreach ($campaign_steps_old as $campaign_step_old) {
                                CampaignStepsVariant::where('campaign_steps_id', $campaign_step_old->id)->delete();
                                CampaignStepsSettings::where('campaign_step_id', $campaign_step_old->id)->delete();
                                $campaign_step_old->delete();
                            }
                        }

                        $campaign_steps = CampaignStepsTemp::where(['campaign_id' => $main_campaign->id])->get()->toArray();

                        if (count($campaign_steps)) {
                            foreach ($campaign_steps as $steps) {
                                $campaign_step['campaign_id'] = $campaign->id;
                                $campaign_step['step_number'] = $steps['step_number'];
                                $campaign_step['next_step_wait_time'] = $steps['next_step_wait_time'];
                                $main_step = CampaignSteps::insert($campaign_step);
                                $step_id = DB::getPdo()->lastInsertId();

                                $variants = CampaignStepsVariantTemp::where('campaign_steps_id', $steps['id'])->orderby('id', 'asc')->get()->toArray();
                                // first loop checks if all data is fine subject is not missing and content has UNSUBSCRIBE_URL
                                if (count($variants)) {
                                    foreach ($variants as $variant) {
                                        if (!$variant['subject']) {
                                            return response()->json(['status' => 0, 'message' => "Subject missing from step number" . $steps['step_number'] . " Variant " . $variant['variant']]);
                                            die;
                                        }
                                        // check id unsubscribe_url tag is present or not
                                        if (!str_contains($variant['content'], '{UNSUBSCRIBE URL}') && !str_contains($variant['content'], '{UNSUBSCRIBE_URL}')) {
                                            return response()->json(['status' => 0, 'message' => "{UNSUBSCRIBE URL} missing from step number" . $steps['step_number'] . " Variant " . $variant['variant']]);
                                            die;
                                        }
                                    }
                                }
                                if (count($variants)) {
                                    foreach ($variants as $variant) {
                                        unset($variant['id']);
                                        unset($variant['created_at']);
                                        unset($variant['updated_at']);
                                        unset($variant['main_campaign_variant_id']);
                                        $variant['campaign_steps_id'] = $step_id;
                                        CampaignStepsVariant::insert($variant);
                                    }
                                }

                                $settings = CampaignStepsSettingsTemp::where('campaign_step_id', $steps['id'])->get()->toArray();
                                if (count($settings)) {
                                    foreach ($settings as $setting) {
                                        unset($setting['id']);
                                        unset($setting['created_at']);
                                        unset($setting['updated_at']);
                                        unset($setting['main_setting_id']);
                                        $setting['campaign_step_id'] = $step_id;
                                        $campaign_setting_temp = CampaignStepsSettings::insert($setting);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            DB::commit();
            // if (!$is_paused) {
            //     $linked_campaigns = json_decode($campaign->linked_campaigns);
            //     foreach ($linked_campaigns as $camp) {
            //         $l_camp = Campaign::find($camp);
            //         $l_camp->resume();
            //     }
            // }
            return response()->json(['status' => 1, 'message' => 'Campaign Updated.']); //code...
        } catch (\Throwable $th) {
            DB::rollBack();
            // throw new Exception($th, 1);


            return response()->json(['status' => 0, 'message' => $th->getMessage()]);
        }
    }
}
