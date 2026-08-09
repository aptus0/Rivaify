<?php

namespace Modules\Commerce\Services\Storefront\RivaLang;

class RivaLangDiagnostic
{
    public function __construct(
        public readonly string $level,
        public readonly string $file,
        public readonly int $line,
        public readonly int $column,
        public readonly string $message,
        public readonly ?string $suggestion = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
