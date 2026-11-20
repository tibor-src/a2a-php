<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class Part
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $text = null,
        public ?string $raw = null,
        public ?string $url = null,
        public mixed $data = null,
        public ?array $metadata = null,
        public ?string $filename = null,
        public ?string $mediaType = null,
    ) {}

    public static function text(string $text, ?string $mediaType = null): self
    {
        return new self(text: $text, mediaType: $mediaType);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $raw = Payload::string($data, 'raw');

        return new self(
            text: Payload::string($data, 'text'),
            raw: $raw === null ? null : Payload::base64ToBytes($raw),
            url: Payload::string($data, 'url'),
            data: $data['data'] ?? null,
            metadata: Payload::object($data, 'metadata'),
            filename: Payload::string($data, 'filename'),
            mediaType: Payload::string($data, 'mediaType'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->text === null && $this->raw === null && $this->url === null && $this->data === null) {
            throw InvalidParamsException::forField('part', 'A part must include text, raw, url, or data.');
        }

        return Payload::omitNulls([
            'text' => $this->text,
            'raw' => $this->raw === null ? null : Payload::bytesToBase64($this->raw),
            'url' => $this->url,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'filename' => $this->filename,
            'mediaType' => $this->mediaType,
        ]);
    }
}
