<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public $temp_group_id;
    public $temp_id_unit;
    public $temp_id_position;

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            if (array_key_exists('group_id', $user->attributes)) {
                $user->temp_group_id = $user->attributes['group_id'];
                unset($user->attributes['group_id']);
            }
            if (array_key_exists('id_unit', $user->attributes)) {
                $user->temp_id_unit = $user->attributes['id_unit'];
                unset($user->attributes['id_unit']);
            }
            if (array_key_exists('id_position', $user->attributes)) {
                $user->temp_id_position = $user->attributes['id_position'];
                unset($user->attributes['id_position']);
            }
        });

        static::saved(function ($user) {
            if (isset($user->temp_group_id)) {
                \DB::table('tb_users_groups')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['group_id' => $user->temp_group_id]
                );
            }
            if (isset($user->temp_id_unit) || isset($user->temp_id_position)) {
                $data = [];
                if (isset($user->temp_id_unit)) {
                    $data['id_unit'] = $user->temp_id_unit;
                }
                if (isset($user->temp_id_position)) {
                    $data['id_position'] = $user->temp_id_position;
                }
                \DB::table('user_unit')->updateOrInsert(
                    ['id_user' => $user->id],
                    $data
                );
            }
        });
    }

    public function groups()
    {
        return $this->belongsToMany(\App\Models\Core\Groups::class, 'tb_users_groups', 'user_id', 'group_id');
    }

    public function getUserGroups()
    {
        return \DB::table('tb_users_groups')
            ->join('tb_groups', 'tb_groups.group_id', '=', 'tb_users_groups.group_id')
            ->where('tb_users_groups.user_id', $this->id)
            ->select('tb_groups.group_id', 'tb_groups.name', 'tb_groups.level')
            ->get();
    }

    public function getUserUnits()
    {
        return \DB::table('user_unit')
            ->leftJoin('unit', 'unit.id_unit', '=', 'user_unit.id_unit')
            ->leftJoin('unit as pos_unit', 'pos_unit.id_unit', '=', 'user_unit.id_position')
            ->where('user_unit.id_user', $this->id)
            ->select(
                'user_unit.id_user_unit',
                'user_unit.id_user',
                'user_unit.id_unit',
                'user_unit.id_position',
                'user_unit.start_date',
                'user_unit.end_date',
                'unit.unit_name',
                'unit.unit_code',
                'unit.id_corporate',
                'pos_unit.unit_name as position_name',
                'pos_unit.unit_code as position_code'
            )
            ->get();
    }

    public function getGroupIdAttribute()
    {
        if (isset($this->attributes['group_id'])) {
            return $this->attributes['group_id'];
        }
        if (isset($this->temp_group_id)) {
            return $this->temp_group_id;
        }
        $group = \DB::table('tb_users_groups')
            ->where('user_id', $this->id)
            ->first();
        return $group ? $group->group_id : null;
    }

    public function getIdUnitAttribute()
    {
        if (isset($this->attributes['id_unit'])) {
            return $this->attributes['id_unit'];
        }
        if (isset($this->temp_id_unit)) {
            return $this->temp_id_unit;
        }
        if (\Auth::check() && \Auth::id() == $this->id && session()->has('id_unit')) {
            return session('id_unit');
        }
        $unit = \DB::table('user_unit')
            ->where('id_user', $this->id)
            ->first();
        return $unit ? $unit->id_unit : null;
    }

    public function getIdPositionAttribute()
    {
        if (isset($this->attributes['id_position'])) {
            return $this->attributes['id_position'];
        }
        if (isset($this->temp_id_position)) {
            return $this->temp_id_position;
        }
        if (\Auth::check() && \Auth::id() == $this->id && session()->has('id_position')) {
            return session('id_position');
        }
        $unit = \DB::table('user_unit')
            ->where('id_user', $this->id)
            ->first();
        return $unit ? $unit->id_position : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'tb_users';
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
