<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use Spatie\Permission\Models\Role;
class NotificationControlsTest extends TestCase
{
 use RefreshDatabase;
 private function notification(User $user, bool $read=false): string {
  $id=(string)Str::uuid();
  DB::table('notifications')->insert(['id'=>$id,'type'=>'TestNotification','notifiable_type'=>$user->getMorphClass(),'notifiable_id'=>$user->id,'data'=>json_encode(['message'=>'Notice '.$id]),'read_at'=>$read?now():null,'created_at'=>now(),'updated_at'=>now()]);
  return $id;
 }
 public function test_individual_and_bulk_changes_only_affect_current_user(): void {
  $user=User::factory()->create();$other=User::factory()->create();
  $id=$this->notification($user);$foreign=$this->notification($other);
  $this->actingAs($user)->post(route('notifications.markRead',$id))->assertRedirect();
  $this->assertNotNull($user->notifications()->find($id)->read_at);
  $this->post(route('notifications.markUnread',$id))->assertRedirect();
  $this->assertNull($user->notifications()->find($id)->read_at);
  $this->post(route('notifications.markRead',$foreign))->assertNotFound();
  $this->post(route('notifications.markUnread',$foreign))->assertNotFound();
  $this->post(route('notifications.markAllRead'))->assertRedirect();
  $this->assertSame(0,$user->unreadNotifications()->count());
  $this->assertSame(1,$other->unreadNotifications()->count());
  $this->post(route('notifications.markAllUnread'))->assertRedirect();
  $this->assertSame(1,$user->unreadNotifications()->count());
 }
 public function test_unread_filter_queries_unread_records_before_limiting(): void {
  Role::findOrCreate('admin');Role::findOrCreate('student');$user=User::factory()->create();$user->assignRole('admin');
  $unread=$this->notification($user);
  DB::table('notifications')->where('id',$unread)->update(['created_at'=>now()->subDay()]);
  for($i=0;$i<9;$i++)$this->notification($user,true);
  $this->actingAs($user)->get(route('admin.dashboard',['notification_filter'=>'unread']))->assertOk()->assertSee('Notice '.$unread)->assertSee('Mark as read')->assertSee('Mark all as unread');
  $this->get(route('admin.dashboard',['notification_filter'=>'all']))->assertOk()->assertDontSee('Notice '.$unread)->assertSee('Mark as unread');
  $other=User::factory()->create();$foreign=$this->notification($other);
  $panel=$this->getJson(route('notifications.panel',['notification_filter'=>'unread']))->assertOk()->assertJsonPath('unread',1);
  $this->assertStringContainsString('Notice '.$unread,$panel->json('html'));
  $this->assertStringNotContainsString('Notice '.$foreign,$panel->json('html'));
 }
}
