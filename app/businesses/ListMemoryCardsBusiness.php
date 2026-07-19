<?php

declare(strict_types=1);

namespace app\businesses;

use app\common\enums\AiGenerationStatus;
use app\common\enums\BusinessCode;
use app\entities\CardLibraryResultEntity;
use app\models\MemoryCard;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ListMemoryCardsBusiness
{
    private const CONTENT_TYPES = ['word', 'sentence'];

    private readonly MemoryCardViewBusiness $views;

    public function __construct(?MemoryCardViewBusiness $views = null)
    {
        $this->views = $views ?? new MemoryCardViewBusiness();
    }

    public function list(int $userId, array $filters): CardLibraryResultEntity
    {
        $validated = $this->validate($filters);
        if ($validated instanceof CardLibraryResultEntity) {
            return $validated;
        }

        $query = MemoryCard::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at');

        $this->applyFilters($query, $userId, $validated);
        if ($validated['cursor'] !== null) {
            [$createdAt, $id] = $validated['cursor'];
            $query->where(static function (Builder $query) use ($createdAt, $id): void {
                $query->where('created_at', '<', $createdAt)
                    ->orWhere(static function (Builder $query) use ($createdAt, $id): void {
                        $query->where('created_at', $createdAt)->where('id', '<', $id);
                    });
            });
        }

        $cards = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($validated['limit'] + 1)
            ->get();
        $hasMore = $cards->count() > $validated['limit'];
        $visible = $cards->take($validated['limit']);
        $items = $visible
            ->map(fn (MemoryCard $card): array => $this->views->build($userId, $card)->card())
            ->values()
            ->all();
        $last = $visible->last();

        return CardLibraryResultEntity::success(
            $items,
            $hasMore && $last instanceof MemoryCard ? $this->encodeCursor($last) : null,
            $hasMore,
        );
    }

    private function validate(array $filters): array|CardLibraryResultEntity
    {
        $limitRaw = (string) ($filters['limit'] ?? '20');
        if (!ctype_digit($limitRaw) || (int) $limitRaw < 1 || (int) $limitRaw > 100) {
            return $this->invalid();
        }

        $contentType = trim((string) ($filters['content_type'] ?? ''));
        if ($contentType !== '' && !in_array($contentType, self::CONTENT_TYPES, true)) {
            return $this->invalid();
        }

        $favoriteRaw = trim((string) ($filters['is_favorite'] ?? ''));
        if ($favoriteRaw !== '' && !in_array($favoriteRaw, ['true', 'false', '1', '0'], true)) {
            return $this->invalid();
        }

        $tagRaw = trim((string) ($filters['tag_id'] ?? ''));
        if ($tagRaw !== '' && (!ctype_digit($tagRaw) || (int) $tagRaw < 1)) {
            return $this->invalid();
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && !in_array($status, array_column(AiGenerationStatus::cases(), 'value'), true)) {
            return $this->invalid();
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if (mb_strlen($search, 'UTF-8') > 200) {
            return $this->invalid();
        }

        $cursor = null;
        $cursorRaw = trim((string) ($filters['cursor'] ?? ''));
        if ($cursorRaw !== '') {
            $cursor = $this->decodeCursor($cursorRaw);
            if ($cursor === null) {
                return CardLibraryResultEntity::failure(BusinessCode::InvalidCursor, '分页游标无效。');
            }
        }

        return [
            'limit' => (int) $limitRaw,
            'content_type' => $contentType,
            'is_favorite' => $favoriteRaw === '' ? null : in_array($favoriteRaw, ['true', '1'], true),
            'tag_id' => $tagRaw === '' ? null : (int) $tagRaw,
            'status' => $status,
            'q' => $search,
            'cursor' => $cursor,
        ];
    }

    private function applyFilters(Builder $query, int $userId, array $filters): void
    {
        if ($filters['content_type'] !== '') {
            $query->where('content_type', $filters['content_type']);
        }
        if ($filters['is_favorite'] !== null) {
            $query->where('is_favorite', $filters['is_favorite']);
        }
        if ($filters['q'] !== '') {
            $pattern = '%' . addcslashes($filters['q'], '\\%_') . '%';
            $query->where(static function (Builder $query) use ($pattern): void {
                $query->where('source_text', 'like', $pattern)
                    ->orWhere('normalized_text', 'like', $pattern);
            });
        }
        if ($filters['tag_id'] !== null) {
            $query->whereExists(static function ($query) use ($userId, $filters): void {
                $query->selectRaw('1')
                    ->from('memory_card_tags')
                    ->whereColumn('memory_card_tags.memory_card_id', 'memory_cards.id')
                    ->where('memory_card_tags.user_id', $userId)
                    ->where('memory_card_tags.tag_id', $filters['tag_id'])
                    ->whereNull('memory_card_tags.deleted_at');
            });
        }
        if ($filters['status'] !== '') {
            $query->whereRaw(
                '(SELECT `job`.`status` FROM `ai_generation_jobs` AS `job` WHERE `job`.`user_id` = ? AND `job`.`memory_card_id` = `memory_cards`.`id` ORDER BY `job`.`id` DESC LIMIT 1) = ?',
                [$userId, $filters['status']],
            );
        }
    }

    private function encodeCursor(MemoryCard $card): string
    {
        $json = json_encode([
            'created_at' => $card->created_at?->format('Y-m-d H:i:s'),
            'id' => (int) $card->id,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): ?array
    {
        $padding = strlen($cursor) % 4;
        $decoded = base64_decode(strtr($cursor . ($padding === 0 ? '' : str_repeat('=', 4 - $padding)), '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $value = json_decode($decoded, true);
        $createdAt = $value['created_at'] ?? null;
        $id = $value['id'] ?? null;
        if (!is_string($createdAt) || !is_int($id) || $id < 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $createdAt);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $createdAt) {
            return null;
        }

        return [$createdAt, $id];
    }

    private function invalid(): CardLibraryResultEntity
    {
        return CardLibraryResultEntity::failure(BusinessCode::InvalidInput, '请求参数不正确。');
    }
}
