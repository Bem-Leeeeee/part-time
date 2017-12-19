<?php

namespace App\Listeners\Employer;

use App\Events\User\WorkApplied;
use App\Model\Employer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Notification;
use App\Notifications\Activity;
use Illuminate\Support\Facades\Redis;
use Vinkla\Pusher\Facades\Pusher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NewApply implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  WorkApplied  $event
     * @return void
     */
    public function handle(WorkApplied $event)
    {
        $user = $event->user;
        $work = $event->work;
        $employer = Employer::find($work->employer_id);
        $notification_type = '申请了兼职';
        $notification = serialize(new Notification($user,$work,$notification_type));
        Redis::lpush('employer:'.$employer->id.':notifications:unread',$notification);
        $followers = $user->userFollowers()->get();
        $action_type = 'uw1';
        $activity = new Activity($user->id,$work->id,$action_type);
        $serialized_activity = serialize($activity);
        foreach ($followers as $follower) {
            Redis::lpush('user:'.$follower->id.':activities',$serialized_activity);
        }
        if (Redis::get('user:'.$employer->id.':online')) {
            Pusher::trigger('employer.' . $employer->id, 'App\Events\NewApply', ['user' => $user,'work' => $work]);
        }
        $user_action = DB::table('user_action')->insert(['user_id' => $user->id,'to_id' => $work->id,'type' => 'uw1','created_at' => Carbon::now(),'updated_at' => Carbon::now()]);
    }
}
