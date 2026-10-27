<?php

namespace App\Modules\SmartSearch\Data;

final class SmartSearchCandidates
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{current_page: int, last_page: int, has_more_pages: bool}  $pagination
     */
    public function __construct(
        public readonly string $mode,
        public readonly string $query,
        public readonly array $items,
        public readonly array $pagination,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     query: string,
     *     items: list<array<string, mixed>>,
     *     pagination: array{current_page: int, last_page: int, has_more_pages: bool}
     * }
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'query' => $this->query,
            'items' => $this->items,
            'pagination' => $this->pagination,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->items === [] || $this->mode === 'empty';
    }
}
