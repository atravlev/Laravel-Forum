<?php

namespace App;

use App\Activity;
use App\Events\ThreadHasNewReply;
use App\Events\ThreadRecivedNewReply;
use App\Filters\ThreadFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use RecordsActivity;

    /**
     * Don't auto-apply mass assignment protection.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relationships to always eager-load.
     *
     * @var array
     */
    protected $with = ['creator', 'channel'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['isSubscribedTo'];

    /**
    * The attributes that should be cast to native types.
    *
    * @var array
    */
    protected $casts = [
        'locked' => 'boolean'
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($thread) {
            $thread->replies->each->delete();
        });

        static::created(function ($thread) {
            $thread->update([
                'slug' => $thread->title
            ]);
        });
    }

    /**
     * Get the route key name for Laravel.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get a string path for the thread.
     *
     * @return string
     */
    public function path()
    {
        return "/threads/{$this->channel->slug}/{$this->slug}";
    }

    /**
     * A thread belongs to a creator.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * A thread is assigned a channel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * A thread may have many replies.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    /**
     * Add a reply to the thread.
     *
     * @param array $reply
     * @return Model
     */
    public function addReply($reply)
    {
        $reply = $this->replies()->create($reply);

        event(new ThreadRecivedNewReply($reply));

        return $reply;
    }

    /**
     * Lock the thread
     */
    public function lock()
    {
        $this->update(['locked' => true]);
    }

    /**
     * Unlock the thread
     */
    public function unlock()
    {
        $this->update(['locked' => false]);
    }

    /**
     * Apply all relevant thread filters.
     *
     * @param  Builder       $query
     * @param  ThreadFilters $filters
     * @return Builder
     */
    public function scopeFilter($query, ThreadFilters $filters)
    {
        return $filters->apply($query);
    }

    /**
     * Subscribe a user to the current thread.
     *
     * @param int|null $userId
     * @return  $this
     */
    public function subscribe($userId = null)
    {
        $this->subscriptions()->create([
            'user_id' => $userId ?: auth()->id()
        ]);

        return $this;
    }

    /**
     * Unsubscribe a user from the current thread.
     *
     * @param int|null $userId
     */
    public function unsubscribe($userId = null)
    {
        $this->subscriptions()
            ->where('user_id', $userId ?: auth()->id())
            ->delete();
    }

    /**
     * A thread can have many subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(ThreadSubscription::class);
    }

    /**
     * Determine if the current user is subscribed to the thread.
     *
     * @return boolean
     */
    public function getIsSubscribedToAttribute()
    {
        return $this->subscriptions()
            ->where('user_id', auth()->id())
            ->exists();
    }

    /**
     * Determine if the thread has been updated since the user last read it.
     *
     * @param  User $user
     * @return bool
     */
    public function hasUpdatesFor($user)
    {
        $key = $user->visitedThreadCacheKey($this);

        return $this->updated_at > cache($key);
    }

    /**
     * Make new instance of Visits associated by the thread
     *
     * @return App\Visit
     */
    public function visits()
    {
        return new Visits($this);
    }

    /**
     * Set the proper slug attribute.
     *
     * @param string $value
     */
    public function setSlugAttribute($value)
    {
        $slug = str_slug($value);

        if (static::whereSlug($slug)->exists()) {
            $slug = "{$slug}-". $this->id;
        }

        $this->attributes['slug'] = $slug;
    }

    /**
     * Mark the given reply as the best answer.
     *
     * @param Reply $reply
     */
    public function markBestReply(Reply $reply)
    {
        $this->update(['best_reply_id' => $reply->id]);
    }
}
