<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\SubmitReviewAnswerBusiness;
use app\controllers\SubmitReviewAnswerController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class SubmitReviewAnswerControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;
    private int $cardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('answer@example.com');
        $this->otherUserId = $this->user('answer-other@example.com');
        $this->cardId = $this->card($this->userId, 'ambition');
    }

    protected function tearDown(): void
    {
        $ids = [$this->userId, $this->otherUserId];
        Db::table('review_events')->whereIn('user_id', $ids)->delete();
        Db::table('daily_learning_stats')->whereIn('user_id', $ids)->delete();
        Db::table('ai_generation_jobs')->whereIn('user_id', $ids)->delete();
        Db::table('memory_cards')->whereIn('user_id', $ids)->delete();
        Db::table('users')->whereIn('id', $ids)->delete();
        parent::tearDown();
    }

    public function test_correct_answer_uses_difficulty_and_updates_schedule_stats_and_sync_once(): void
    {
        $response = $this->submit($this->userId, $this->cardId, 'answer-1', 'zh_to_en', ' Ambition! ', 'good', 1200);
        self::assertSame(200, $response->getStatusCode());
        $data = $this->payload($response)['data'];
        self::assertTrue($data['is_correct']);
        self::assertSame('good', $data['effective_rating']);
        self::assertSame(1, $data['next_stage']);
        self::assertSame(10, $data['xp_awarded']);
        $card = (array) Db::table('memory_cards')->where('id', $this->cardId)->first();
        self::assertSame(1, (int) $card['review_count']);
        self::assertNotNull($card['first_reviewed_at']);
        self::assertSame((int) $card['sync_version'], (int) Db::table('users')->where('id', $this->userId)->value('sync_version'));
        self::assertSame(1, Db::table('review_events')->where('user_id', $this->userId)->count());
        $stat = (array) Db::table('daily_learning_stats')->where('user_id', $this->userId)->first();
        self::assertSame(1, (int) $stat['reviews_completed']);
        self::assertSame(1, (int) $stat['correct_reviews']);
        self::assertSame(10, (int) $stat['xp_earned']);
        self::assertSame(1, (int) $stat['current_correct_streak']);
    }

    public function test_incorrect_answer_forces_again_and_resets_correct_streak(): void
    {
        $this->submit($this->userId, $this->cardId, 'answer-correct', 'listening_spelling', 'ambition', 'easy', 100);
        $response = $this->submit($this->userId, $this->cardId, 'answer-wrong', 'zh_to_en', 'ambitious', 'easy', 200);
        $data = $this->payload($response)['data'];
        self::assertFalse($data['is_correct']);
        self::assertSame('again', $data['effective_rating']);
        self::assertSame(0, $data['next_stage']);
        self::assertSame(2, $data['xp_awarded']);
        self::assertSame(0, (int) Db::table('daily_learning_stats')->where('user_id', $this->userId)->value('current_correct_streak'));
    }

    public function test_identical_idempotency_replays_stored_result_and_changed_request_conflicts(): void
    {
        $first = $this->submit($this->userId, $this->cardId, 'answer-replay', 'zh_to_en', 'ambition', 'hard', 500);
        $second = $this->submit($this->userId, $this->cardId, 'answer-replay', 'zh_to_en', 'ambition', 'hard', 500);
        self::assertSame($first->rawBody(), $second->rawBody());
        self::assertSame(1, Db::table('review_events')->where('user_id', $this->userId)->count());
        self::assertSame(1, Db::table('daily_learning_stats')->where('user_id', $this->userId)->value('reviews_completed'));
        $conflict = $this->submit($this->userId, $this->cardId, 'answer-replay', 'zh_to_en', 'wrong', 'hard', 500);
        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame('IDEMPOTENCY_CONFLICT', $this->payload($conflict)['error']['code']);
    }

    public function test_it_rejects_missing_key_unavailable_mode_and_foreign_card(): void
    {
        $missing = $this->submit($this->userId, $this->cardId, '', 'zh_to_en', 'ambition', 'good', 1);
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame('IDEMPOTENCY_KEY_REQUIRED', $this->payload($missing)['error']['code']);
        $mode = $this->submit($this->userId, $this->cardId, 'bad-mode', 'image_recall', 'ambition', 'good', 1);
        self::assertSame(422, $mode->getStatusCode());
        self::assertSame('INVALID_REVIEW_MODE', $this->payload($mode)['error']['code']);
        $foreignCard = $this->card($this->otherUserId, 'foreign');
        $foreign = $this->submit($this->userId, $foreignCard, 'foreign', 'zh_to_en', 'foreign', 'good', 1);
        self::assertSame(404, $foreign->getStatusCode());
    }

    private function submit(int $userId, int $cardId, string $key, string $mode, string $answer, string $difficulty, int $responseMs): \Webman\Http\Response
    {
        $body = json_encode(compact('mode', 'answer', 'difficulty') + ['response_ms' => $responseMs], JSON_THROW_ON_ERROR);
        $request = new Request("POST /api/reviews/{$cardId}/answer HTTP/1.1\r\nHost: localhost\r\nIdempotency-Key: {$key}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}");
        $request->setAuthenticatedUserId($userId);
        return (new SubmitReviewAnswerController(new SubmitReviewAnswerBusiness()))($request, $cardId);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId(['email'=>$email,'password_hash'=>password_hash('SecurePass123!', PASSWORD_DEFAULT),'status'=>'active','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    }

    private function card(int $userId, string $word): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('memory_cards')->insertGetId(['user_id'=>$userId,'source_text'=>$word,'normalized_text'=>$word,'content_type'=>'word','memory_style'=>'auto','card_payload'=>json_encode(['word'=>$word]),'review_stage'=>0,'created_at'=>$now,'updated_at'=>$now]);
        $request = ['text'=>$word];
        Db::table('ai_generation_jobs')->insert(['user_id'=>$userId,'memory_card_id'=>$id,'idempotency_key'=>'answer-job-'.$id,'request_hash'=>hash('sha256', json_encode($request)),'request_payload'=>json_encode($request),'status'=>'completed','attempts'=>1,'created_at'=>$now,'updated_at'=>$now]);
        return $id;
    }

    private function payload(\Webman\Http\Response $response): array { return json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR); }
}
