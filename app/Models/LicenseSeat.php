<?php

namespace App\Models;

use App\Models\Traits\Acceptable;
use App\Notifications\CheckinLicenseNotification;
use App\Notifications\CheckoutLicenseNotification;
use App\Presenters\Presentable;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseSeat extends SnipeModel implements ICompanyableChild
{
    use CompanyableChildTrait;
    use SoftDeletes;
    use Loggable;

    protected $presenter = \App\Presenters\LicenseSeatPresenter::class;
    use Presentable;

    protected $guarded = 'id';
    protected $table = 'license_seats';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'assigned_to',
        'asset_id',
    ];

    use Acceptable;

    public function getCompanyableParents()
    {
        return ['asset', 'license'];
    }

    /**
     * Determine whether the user should be required to accept the license
     *
     * @author
     * @since [v4.0]
     * @return bool
     */
    public function requireAcceptance()
    {
        return $this->license->category->require_acceptance;
    }

    public function getEula()
    {
        return $this->license->getEula();
    }

    /**
     * Establishes the seat -> license relationship
     *
     * @author
     * @since [v1.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function license()
    {
        return $this->belongsTo(\App\Models\License::class, 'license_id');
    }

    /**
     * Establishes the seat -> assignee relationship
     *
     * @author
     * @since [v1.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to')->withTrashed();
    }

    /**
     * Establishes the seat -> asset relationship
     *
     * @author
     * @since [v4.0]
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function asset()
    {
        return $this->belongsTo(\App\Models\Asset::class, 'asset_id')->withTrashed();
    }

    /**
     * Determines the assigned seat's location based on user
     * or asset its assigned to
     *
     * @author
     * @since [v4.0]
     * @return string
     */
    public function location()
    {
        if (($this->user) && ($this->user->location)) {
            return $this->user->location;
        } elseif (($this->asset) && ($this->asset->location)) {
            return $this->asset->location;
        }

        return false;
    }

    /**
     * Query builder scope to order on department
     *
     * @param  \Illuminate\Database\Query\Builder  $query  Query builder instance
     * @param  text                              $order         Order
     *
     * @return \Illuminate\Database\Query\Builder          Modified query builder
     */
    public function scopeOrderDepartments($query, $order)
    {
        return $query->leftJoin('users as license_seat_users', 'license_seats.assigned_to', '=', 'license_seat_users.id')
            ->leftJoin('departments as license_user_dept', 'license_user_dept.id', '=', 'license_seat_users.department_id')
            ->orderBy('license_user_dept.name', $order);
    }
}
