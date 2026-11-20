<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentSkill
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $examples
     * @param  list<string>  $inputModes
     * @param  list<string>  $outputModes
     * @param  list<SecurityRequirement>  $securityRequirements
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $tags,
        public array $examples = [],
        public array $inputModes = [],
        public array $outputModes = [],
        public array $securityRequirements = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $requirements = Payload::list($data, 'securityRequirements') ?? [];

        return new self(
            id: Payload::requireString($data, 'id'),
            name: Payload::requireString($data, 'name'),
            description: Payload::requireString($data, 'description'),
            tags: Payload::optionalStrings($data, 'tags'),
            examples: Payload::optionalStrings($data, 'examples'),
            inputModes: Payload::optionalStrings($data, 'inputModes'),
            outputModes: Payload::optionalStrings($data, 'outputModes'),
            securityRequirements: Payload::mapObjects($requirements, SecurityRequirement::fromArray(...), 'securityRequirements'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tags' => $this->tags,
            'examples' => $this->examples === [] ? null : $this->examples,
            'inputModes' => $this->inputModes === [] ? null : $this->inputModes,
            'outputModes' => $this->outputModes === [] ? null : $this->outputModes,
            'securityRequirements' => $this->securityRequirements === [] ? null : array_map(
                fn (SecurityRequirement $requirement): array => $requirement->toArray(),
                $this->securityRequirements,
            ),
        ]);
    }
}
