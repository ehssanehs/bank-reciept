<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'group', 'description'];

    public const VIEW_PAYMENTS = 'view_payments';
    public const VIEW_RECEIPTS = 'view_receipts';
    public const VIEW_BANK_SMS = 'view_bank_sms';
    public const APPROVE_PAYMENTS = 'approve_payments';
    public const REJECT_PAYMENTS = 'reject_payments';
    public const MANAGE_MATCHING_RULES = 'manage_matching_rules';
    public const MANAGE_BANKS = 'manage_banks';
    public const MANAGE_BANK_PARSERS = 'manage_bank_parsers';
    public const MANAGE_TELEGRAM = 'manage_telegram';
    public const MANAGE_USERS = 'manage_users';
    public const MANAGE_SETTINGS = 'manage_settings';
    public const VIEW_AUDIT = 'view_audit';
    public const VIEW_REPORTS = 'view_reports';
    public const MANAGE_DEVICES = 'manage_devices';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }
}
