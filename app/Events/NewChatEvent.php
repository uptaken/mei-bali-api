<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\ConsultationRoom;
use App\Http\Controllers\Helper\RelationshipHelper;

class NewChatEvent implements ShouldBroadcastNow
{
  use Dispatchable, InteractsWithSockets, SerializesModels;
  public $chat_room;

  /**
  * Create a new event instance.
  *
  * @return void
  */
  public function __construct($chat_room){
    $relationship_helper = new RelationshipHelper();
    $this->chat_room = $chat_room;
  }

  /**
  * Get the channels the event should broadcast on.
  *
  * @return \Illuminate\Broadcasting\Channel|array
  */
  public function broadcastOn()
  {
    return new Channel('new_chat.'.$this->chat_room->chat->id);
  }
}
