<?php

declare(strict_types=1);

namespace Tests\Feature;

use app\businesses\GetTodayReviewsBusiness;
use app\controllers\GetTodayReviewsController;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class GetTodayReviewsControllerTest extends TestCase
{
    private int $userId;
    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = $this->user('today@example.com');
        $this->otherUserId = $this->user('today-other@example.com');
    }

    protected function tearDown(): void
    {
        $ids = [$this->userId, $this->otherUserId];
        Db::table('ai_generation_jobs')->whereIn('user_id', $ids)->delete();
        Db::table('memory_cards')->whereIn('user_id', $ids)->delete();
        Db::table('users')->whereIn('id', $ids)->delete();
        parent::tearDown();
    }

    public function test_it_returns_all_due_cards_before_at_most_twenty_new_cards(): void
    {
        $dueA = $this->card($this->userId, 'due-a', '2020-01-01 00:00:00', '2020-01-01 00:00:00');
        $dueB = $this->card($this->userId, 'due-b', '2020-01-02 00:00:00', '2020-01-02 00:00:00');
        $newIds = [];
        for ($i = 0; $i < 22; $i++) { $newIds[] = $this->card($this->userId, 'new-' . $i); }
        $this->card($this->otherUserId, 'foreign');
        $this->card($this->userId, 'failed', null, null, 'failed');

        $response = $this->request();
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
        $ids = array_column(array_column($data['items'], 'card'), 'id');
        self::assertSame([$dueA, $dueB], array_slice($ids, 0, 2));
        self::assertSame(array_slice($newIds, 0, 20), array_slice($ids, 2));
        self::assertCount(22, $ids);
        self::assertSame('Asia/Shanghai', $data['timezone']);
        self::assertSame(['listening_spelling', 'zh_to_en'], $data['items'][0]['available_modes']);
    }

    public function test_it_returns_an_empty_queue_for_user_without_reviewable_cards(): void
    {
        $data = json_decode($this->request()->rawBody(), true, 512, JSON_THROW_ON_ERROR)['data'];
        self::assertSame([], $data['items']);
    }

    private function request(): \Webman\Http\Response
    {
        $request = new Request("GET /api/reviews/today HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setAuthenticatedUserId($this->userId);
        return (new GetTodayReviewsController(new GetTodayReviewsBusiness()))($request);
    }

    private function user(string $email): int
    {
        return (int) Db::table('users')->insertGetId(['email'=>$email,'password_hash'=>password_hash('SecurePass123!', PASSWORD_DEFAULT),'status'=>'active','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
    }

    private function card(int $userId, string $word, ?string $due = null, ?string $firstReviewed = null, string $status = 'completed'): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('memory_cards')->insertGetId(['user_id'=>$userId,'source_text'=>$word,'normalized_text'=>$word,'content_type'=>'word','memory_style'=>'auto','card_payload'=>json_encode(['word'=>$word]),'next_review_at'=>$due,'first_reviewed_at'=>$firstReviewed,'review_count'=>$firstReviewed === null ? 0 : 1,'review_stage'=>0,'created_at'=>$now,'updated_at'=>$now]);
        $request = ['text'=>$word];
        Db::table('ai_generation_jobs')->insert(['user_id'=>$userId,'memory_card_id'=>$id,'idempotency_key'=>'today-'.$id,'request_hash'=>hash('sha256', json_encode($request)),'request_payload'=>json_encode($request),'status'=>$status,'attempts'=>1,'created_at'=>$now,'updated_at'=>$now]);
        return $id;
    }
}
