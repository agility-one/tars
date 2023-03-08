<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memory;
use App\Models\User;
use App\Http\Controllers\MemoryController;
use App\Http\Controllers\OpenAIAPIController as OpenAI;
use Inertia\Inertia;

class ChatController extends Controller
{

	public function index()
	{
		$data = [];
		$data['memories'] = Memory::all()->map(
			function ($memory) {
				$memory['speaker'] = User::find($memory->speaker_id)->name;
				$memory['date'] = $this->human_readable_date($memory->created_at, true);
				return $memory;
			}
		)->toArray();
		return Inertia::render('Chat', ['data' => $data]);
	}

	public function store(Request $request)
	{

		// Preform some validation.
		$request->validate([
			'message' => 'required',
		]);

		// Create the Memory.
		$memory = Memory::create([
			'speaker_id' => env('CHAT_USER_ID'),
			'message' => $request->input('message'),
			'vector' => OpenAI::gpt3_embedding($request->input('message')),
		]);

		MemoryController::generate_reply($memory->vector);
		return to_route('chat.index');
	}

	public function human_readable_date($date, $full = false)
	{
		$now = new \DateTime();
		$ago = new \DateTime($date);
		$diff = $now->diff($ago);
		$diff->w = floor($diff->d / 7);
		$diff->d -= $diff->w * 7;
		$string = array(
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		);
		foreach ($string as $k => &$v) {
			if ($diff->$k) {
				$v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
			} else {
				unset($string[$k]);
			}
		}
		if (! $full ) $string = array_slice($string, 0, 1);
		return $string ? implode(', ', $string) . ' ago' : 'just now';
	}
}
