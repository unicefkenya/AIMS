<?php

namespace App\Console\Commands;

use App\Models\CustomField;
use Illuminate\Console\Command;

class ReEncodeCustomFieldNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bewsys:regenerate-fieldnames';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This utility will regenerate the column names for custom fields. It should typically only be needed when a PHP upgrade changed the behavior of the unicode conversion between versions.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * All three of these things must match for the custom fields system to work as expected:
     *
     * - what the system thinks the output of $field->convertUnicodeDbSlug() is
     * - the actual db_column name in the customfields table
     * - the physical column name that was created on the assets table
     *
     * For some people who upgraded their version of PHP, the unicode converter now behaves
     * differently in than it did when their custom fields were first created, specifically as it
     * relates to handling slashes, ampersands, etc. This can result in the field names no longer
     * matching up, as an older version of the PHP extension simply dropped slashes, etc, while the
     * newer version of the PHP extension will convert them to underscores.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->confirm('This will regenerate all of the custom field database fieldnames in your database. THIS WILL CHANGE YOUR SCHEMA AND SHOULD NOT BE DONE WITHOUT MAKING A BACKUP FIRST. Do you wish to continue?')) {

            /** Get all of the custom fields */
            $fields = CustomField::get();

            $asset_columns = \DB::getSchemaBuilder()->getColumnListing('assets');
            $custom_field_columns = [];

            /** Loop through the columns on the assets table */
            foreach ($asset_columns as $asset_column) {

                /** Add ones that start with _bewsys_ to an array for handling */
                if (strpos($asset_column, '_bewsys_') === 0) {

                    /**
                     * Get the ID of the custom field based on the fieldname.
                     * For example, in _bewsys_mac_address_1, we grab the 1 because we know
                     * that's the ID of the custom field that created the column.
                     * Then use that ID as the array key for use comparing the actual assets field name
                     * and the db_column value from the custom fields table.
                     */
                    $last_part = substr(strrchr($asset_column, '_bewsys_'), 1);
                    $custom_field_columns[$last_part] = $asset_column;
                }
            }

            foreach ($fields as $field) {
                $this->info($field->name.' ('.$field->id.') column should be '.$field->convertUnicodeDbSlug().'');

                /** The assets table has the column it should have, all is well */
                if (\Schema::hasColumn('assets', $field->convertUnicodeDbSlug())) {
                    $this->info('-- ✓ This field exists - all good');

                /**
                 * There is a mismatch between the fieldname on the assets table and
                 * what $field->convertUnicodeDbSlug() is *now* expecting.
                 */
                } else {
                    $this->warn('-- X Field mismatch: updating... ');

                    /** Make sure the custom_field_columns array has the ID */
                    if (array_key_exists($field->id, $custom_field_columns)) {

                        /**
                         * Update the asset schema to the corrected fieldname that will be recognized by the
                         *  system elsewhere that we use $field->convertUnicodeDbSlug()
                         */
                        \Schema::table('assets', function ($table) use ($custom_field_columns, $field) {
                            $table->renameColumn($custom_field_columns[$field->id], $field->convertUnicodeDbSlug());
                        });

                        $this->warn('-- ✓ Field updated from '.$custom_field_columns[$field->id].' to '.$field->convertUnicodeDbSlug());
                    } else {
                        $this->warn('-- X WARNING: There is no field on the assets table ending in  '.$field->id.'. This may require more in-depth investigation and may mean the schema was altered manually.');
                    }
                }

                /** Update the db_column property in the custom fields table, just in case it doesn't match the other
                 * things.
                 */
                $field->db_column = $field->convertUnicodeDbSlug();
                $field->save();
            }
        }
    }
}
