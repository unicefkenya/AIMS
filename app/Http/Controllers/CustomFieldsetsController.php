<?php

namespace App\Http\Controllers;

use App\Models\AssetModel;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Redirect;

/**
 * This controller handles all actions related to Custom Asset Fields for
 * the Bewsys Asset Management application.
 *
 * @todo Improve documentation here.
 * @todo Check for raw DB queries and try to convert them to query builder statements
 * @version    v2.0
 * @author [Brady Wetherington] [<uberbrady@gmail.com>]
 */
class CustomFieldsetsController extends Controller
{

    public function index() 
    {
        return redirect()->route("fields.index")
        ->with("error", trans('admin/custom_fields/message.fieldset.does_not_exist'));
    }

    /**
     * Validates and stores a new custom field.
     *
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @param int $id
     * @return \Illuminate\Support\Facades\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @since [v1.8]
     */
    public function show($id)
    {
        $cfset = CustomFieldset::with('fields')
            ->where('id', '=', $id)->orderBy('id', 'ASC')->first();

        $this->authorize('view', $cfset);

        if ($cfset) {
            $custom_fields_list = ['' => 'Add New Field to Fieldset'] + CustomField::pluck('name', 'id')->toArray();

            $maxid = 0;
            foreach ($cfset->fields as $field) {
                if ($field->pivot->order > $maxid) {
                    $maxid = $field->pivot->order;
                }
                if (isset($custom_fields_list[$field->id])) {
                    unset($custom_fields_list[$field->id]);
                }
            }

            return view('custom_fields.fieldsets.view')->with('custom_fieldset', $cfset)->with('maxid', $maxid + 1)->with('custom_fields_list', $custom_fields_list);
        }

        return redirect()->route('fields.index')
            ->with('error', trans('admin/custom_fields/message.fieldset.does_not_exist'));
    }

    /**
     * Returns a view with a form for creating a new custom fieldset.
     *
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @since [v1.8]
     * @return \Illuminate\Support\Facades\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', CustomFieldset::class);

        return view('custom_fields.fieldsets.edit');
    }

    /**
     * Validates and stores a new custom fieldset.
     *
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @since [v1.8]
     * @param Request $request
     * @return Redirect
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', CustomFieldset::class);

        $cfset = new CustomFieldset([
                'name' => e($request->get('name')),
                'user_id' => Auth::user()->id,
        ]);

        $validator = Validator::make($request->all(), $cfset->rules);
        if ($validator->passes()) {
            $cfset->save();

            return redirect()->route('fieldsets.show', [$cfset->id])
                ->with('success', trans('admin/custom_fields/message.fieldset.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($validator);
    }

    /**
     * What the actual fuck, Brady?
     *
     * @todo Uhh, build this?
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @param  int  $id
     * @since [v1.8]
     * @return Fuckall
     */
    public function edit($id)
    {
        //
    }

    /**
     * GET IN THE SEA BRADY.
     *
     * @todo Uhh, build this too?
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @param  int  $id
     * @since [v1.8]
     * @return Fuckall
     */
    public function update($id)
    {
        //
    }

    /**
     * Validates a custom fieldset and then deletes if it has no models associated.
     *
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @param  int $id
     * @since [v1.8]
     * @return View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy($id)
    {
        $fieldset = CustomFieldset::find($id);

        $this->authorize('delete', $fieldset);

        if ($fieldset) {
            $models = AssetModel::where('fieldset_id', '=', $id);
            if ($models->count() == 0) {
                $fieldset->delete();

                return redirect()->route('fields.index')->with('success', trans('admin/custom_fields/message.fieldset.delete.success'));
            }

            return redirect()->route('fields.index')->with('error', trans('admin/custom_fields/message.fieldset.delete.in_use'));
        }

        return redirect()->route('fields.index')->with('error', trans('admin/custom_fields/message.fieldset.does_not_exist'));
    }

    /**
     * Associate the custom field with a custom fieldset.
     *
     * @author [Brady Wetherington] [<uberbrady@gmail.com>]
     * @since [v1.8]
     * @return View
     */
    public function associate(Request $request, $id)
    {
        $set = CustomFieldset::find($id);

        $this->authorize('update', $set);

        if ($request->filled('field_id')) {
            foreach ($set->fields as $field) {
                if ($field->id == $request->input('field_id')) {
                    return redirect()->route('fieldsets.show', [$id])->withInput()->withErrors(['field_id' => trans('admin/custom_fields/message.field.already_added')]);
                }
            }

            $results = $set->fields()->attach($request->input('field_id'), ['required' => ($request->input('required') == 'on'), 'order' => (int)$request->input('order', 1)]);

            return redirect()->route('fieldsets.show', [$id])->with('success', trans('admin/custom_fields/message.field.create.assoc_success'));
        }

        return redirect()->route('fieldsets.show', [$id])->with('error', 'No field selected.');
    }

    /**
     * Set the field in a fieldset to required
     *
     * @author 
     * @since [v5.0]
     */
    public function makeFieldRequired($fieldset_id, $field_id)
    {
        $this->authorize('update', CustomFieldset::class);
        $field = CustomField::findOrFail($field_id);
        $fieldset = CustomFieldset::findOrFail($fieldset_id);
        $fields[$field->id] = ['required' => 1];
        $fieldset->fields()->syncWithoutDetaching($fields);

        return redirect()->route('fieldsets.show', ['fieldset' => $fieldset_id])
            ->with('success', trans('Field successfully set to required'));
    }

    /**
     * Set the field in a fieldset to optional
     *
     * @author 
     * @since [v5.0]
     */
    public function makeFieldOptional($fieldset_id, $field_id)
    {
        $this->authorize('update', CustomFieldset::class);
        $field = CustomField::findOrFail($field_id);
        $fieldset = CustomFieldset::findOrFail($fieldset_id);
        $fields[$field->id] = ['required' => 0];
        $fieldset->fields()->syncWithoutDetaching($fields);

        return redirect()->route('fieldsets.show', ['fieldset' => $fieldset_id])
            ->with('success', trans('Field successfully set to optional'));
    }
}
