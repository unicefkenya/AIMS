<?php
namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Actionlog;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class ActionlogsTransformer
{

    public function transformActionlogs (Collection $actionlogs, $total)
    {
        $array = array();
        $settings = Setting::getSettings();
        foreach ($actionlogs as $actionlog) {
            $array[] = self::transformActionlog($actionlog, $settings);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    private function clean_field($value)
    {
        // This object stuff is weird, and is used to make up for the fact that
        // older data can get strangely formatted if an asset existed,
        // then a new custom field is added, and the asset is saved again.
        // It can result in funnily-formatted strings like:
        //
        // {"_bewsys_right_sized_fault_tolerant_localareanetwo_1":
        // {"old":null,"new":{"value":"1579490695972","_bewsys_new_field_2":2,"_bewsys_new_field_3":"Monday, 20 January 2020 2:24:55 PM"}}
        // so we have to walk down that next level
        if(is_object($value) && isset($value->value)) {
            return $this->clean_field($value->value);
        }
        return is_scalar($value) || is_null($value) ? e($value) : e(json_encode($value));
    }

    public function transformActionlog (Actionlog $actionlog, $settings = null)
    {
        $icon = $actionlog->present()->icon();
        if ($actionlog->filename!='') {
            $icon =  e(\App\Helpers\Helper::filetype_icon($actionlog->filename));
        }

        // This is necessary since we can't escape special characters within a JSON object
        if (($actionlog->log_meta) && ($actionlog->log_meta!='')) {
            $meta_array = json_decode($actionlog->log_meta);

            if ($meta_array) {
                foreach ($meta_array as $fieldname => $fieldata) {
                    $clean_meta[$fieldname]['old'] = $this->clean_field($fieldata->old);
                    $clean_meta[$fieldname]['new'] = $this->clean_field($fieldata->new);
                }

            }
        }

        $file_url = '';
        if($actionlog->filename!='') {
            if ($actionlog->present()->actionType() == 'accepted') {
                $file_url = route('log.storedeula.download', ['filename' => $actionlog->filename]);
            } else {
                if ($actionlog->itemType() == 'asset') {
                    $file_url = route('show/assetfile', ['assetId' => $actionlog->id, 'fileId' => $actionlog->id]);
                } elseif ($actionlog->itemType() == 'license') {
                    $file_url = route('show.licensefile', ['licenseId' => $actionlog->item->id, 'fileId' => $actionlog->id]);
                } elseif ($actionlog->itemType() == 'user') {
                    $file_url = route('show/userfile', ['userId' => $actionlog->item->id, 'fileId' => $actionlog->id]);
                }
            }
        }

        $array = [
            'id'          => (int) $actionlog->id,
            'icon'          => $icon,
            'file' => ($actionlog->filename!='')
                ?
                [
                    'url' => $file_url,
                    'filename' => $actionlog->filename,
                    'inlineable' => (bool) Helper::show_file_inline($actionlog->filename),
                ] : null,

            'item' => ($actionlog->item) ? [
                'id' => (int) $actionlog->item->id,
                'name' => ($actionlog->itemType()=='user') ? e($actionlog->item->getFullNameAttribute()) : e($actionlog->item->getDisplayNameAttribute()),
                'type' => e($actionlog->itemType()),
            ] : null,
            'location' => ($actionlog->location) ? [
                'id' => (int) $actionlog->location->id,
                'name' => e($actionlog->location->name),
            ] : null,
            'created_at'    => Helper::getFormattedDateObject($actionlog->created_at, 'datetime'),
            'updated_at'    => Helper::getFormattedDateObject($actionlog->updated_at, 'datetime'),
            'next_audit_date' => ($actionlog->itemType()=='asset') ? Helper::getFormattedDateObject($actionlog->calcNextAuditDate(null, $actionlog->item), 'date'): null,
            'days_to_next_audit' => $actionlog->daysUntilNextAudit($settings->audit_interval, $actionlog->item),
            'action_type'   => $actionlog->present()->actionType(),
            'admin' => ($actionlog->user) ? [
                'id' => (int) $actionlog->user->id,
                'name' => e($actionlog->user->getFullNameAttribute()),
                'first_name'=> e($actionlog->user->first_name),
                'last_name'=> e($actionlog->user->last_name)
            ] : null,
            'target' => ($actionlog->target) ? [
                'id' => (int) $actionlog->target->id,
                'name' => ($actionlog->targetType()=='user') ? e($actionlog->target->getFullNameAttribute()) : e($actionlog->target->getDisplayNameAttribute()),
                'type' => e($actionlog->targetType()),
            ] : null,

            'note'          => ($actionlog->note) ? e($actionlog->note): null,
            'signature_file'   => ($actionlog->accept_signature) ? route('log.signature.view', ['filename' => $actionlog->accept_signature ]) : null,
            'log_meta'          => ((isset($clean_meta)) && (is_array($clean_meta))) ? $clean_meta: null,
            'action_date'   => ($actionlog->action_date) ? Helper::getFormattedDateObject($actionlog->action_date, 'datetime'): Helper::getFormattedDateObject($actionlog->created_at, 'datetime'),
        ];
        //\Log::info("Clean Meta is: ".print_r($clean_meta,true));

        //dd($array);
        return $array;
    }



    public function transformCheckedoutActionlog (Collection $accessories_users, $total)
    {

        $array = array();
        foreach ($accessories_users as $user) {
            $array[] = (new UsersTransformer)->transformUser($user);
        }
        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }



}