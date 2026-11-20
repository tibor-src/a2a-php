<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class AgentCard
{
    /**
     * @param  list<AgentInterface>  $supportedInterfaces
     * @param  list<string>  $defaultInputModes
     * @param  list<string>  $defaultOutputModes
     * @param  list<AgentSkill>  $skills
     * @param  array<string, SecurityScheme>  $securitySchemes
     * @param  list<SecurityRequirement>  $securityRequirements
     * @param  list<AgentCardSignature>  $signatures
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $supportedInterfaces,
        public string $version,
        public AgentCapabilities $capabilities,
        public array $defaultInputModes,
        public array $defaultOutputModes,
        public array $skills,
        public ?AgentProvider $provider = null,
        public ?string $documentationUrl = null,
        public array $securitySchemes = [],
        public array $securityRequirements = [],
        public array $signatures = [],
        public ?string $iconUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $interfaces = Payload::list($data, 'supportedInterfaces') ?? [];
        $skills = Payload::list($data, 'skills') ?? [];
        $requirements = Payload::list($data, 'securityRequirements') ?? [];
        $signatures = Payload::list($data, 'signatures') ?? [];
        $provider = Payload::object($data, 'provider');
        $schemes = Payload::object($data, 'securitySchemes') ?? [];

        if ($interfaces === []) {
            throw InvalidParamsException::forField('supportedInterfaces', 'At least one interface is required.');
        }

        $mappedSchemes = [];

        foreach ($schemes as $name => $scheme) {
            if (! is_array($scheme)) {
                throw InvalidParamsException::forField('securitySchemes', 'Each scheme must be an object.');
            }

            /** @var array<string, mixed> $scheme */
            $mappedSchemes[$name] = SecurityScheme::fromArray($scheme);
        }

        return new self(
            name: Payload::requireString($data, 'name'),
            description: Payload::requireString($data, 'description'),
            supportedInterfaces: Payload::mapObjects($interfaces, AgentInterface::fromArray(...), 'supportedInterfaces'),
            version: Payload::requireString($data, 'version'),
            capabilities: AgentCapabilities::fromArray(Payload::requireObject($data, 'capabilities')),
            defaultInputModes: Payload::optionalStrings($data, 'defaultInputModes'),
            defaultOutputModes: Payload::optionalStrings($data, 'defaultOutputModes'),
            skills: Payload::mapObjects($skills, AgentSkill::fromArray(...), 'skills'),
            provider: $provider === null ? null : AgentProvider::fromArray($provider),
            documentationUrl: Payload::string($data, 'documentationUrl'),
            securitySchemes: $mappedSchemes,
            securityRequirements: Payload::mapObjects($requirements, SecurityRequirement::fromArray(...), 'securityRequirements'),
            signatures: Payload::mapObjects($signatures, AgentCardSignature::fromArray(...), 'signatures'),
            iconUrl: Payload::string($data, 'iconUrl'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schemes = [];

        foreach ($this->securitySchemes as $name => $scheme) {
            $schemes[$name] = $scheme->toArray();
        }

        return Payload::omitNulls([
            'name' => $this->name,
            'description' => $this->description,
            'supportedInterfaces' => array_map(
                fn (AgentInterface $interface): array => $interface->toArray(),
                $this->supportedInterfaces,
            ),
            'provider' => $this->provider?->toArray(),
            'version' => $this->version,
            'documentationUrl' => $this->documentationUrl,
            'capabilities' => $this->capabilities->toArray(),
            'securitySchemes' => $schemes === [] ? null : $schemes,
            'securityRequirements' => $this->securityRequirements === [] ? null : array_map(
                fn (SecurityRequirement $requirement): array => $requirement->toArray(),
                $this->securityRequirements,
            ),
            'defaultInputModes' => $this->defaultInputModes,
            'defaultOutputModes' => $this->defaultOutputModes,
            'skills' => array_map(fn (AgentSkill $skill): array => $skill->toArray(), $this->skills),
            'signatures' => $this->signatures === [] ? null : array_map(
                fn (AgentCardSignature $signature): array => $signature->toArray(),
                $this->signatures,
            ),
            'iconUrl' => $this->iconUrl,
        ]);
    }

    public function preferredInterface(string $binding): ?AgentInterface
    {
        foreach ($this->supportedInterfaces as $interface) {
            if ($interface->protocolBinding === $binding) {
                return $interface;
            }
        }

        return $this->supportedInterfaces[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function acceptedInputModes(): array
    {
        $modes = $this->defaultInputModes;

        foreach ($this->skills as $skill) {
            $modes = [...$modes, ...$skill->inputModes];
        }

        return array_values(array_unique($modes));
    }
}
