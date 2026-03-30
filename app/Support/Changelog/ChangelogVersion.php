<?php

namespace App\Support\Changelog;

use App\Models\ChangelogRelease;
use InvalidArgumentException;

class ChangelogVersion
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public ?string $suffix,
        public string $channel,
    ) {}

    public static function fromRelease(ChangelogRelease $release): self
    {
        return new self(
            major: (int) $release->version_major,
            minor: (int) $release->version_minor,
            patch: (int) $release->version_patch,
            suffix: $release->version_suffix,
            channel: (string) $release->channel,
        );
    }

    public static function parse(string $versionLabel, ?string $channel = null): self
    {
        $normalized = trim($versionLabel);

        if (! preg_match('/^(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)(?:-(?<suffix>[A-Za-z0-9][A-Za-z0-9.\-]*))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('The changelog version label is invalid.');
        }

        $suffix = $matches['suffix'] ?? null;
        $normalizedChannel = $channel !== null && trim($channel) !== ''
            ? trim($channel)
            : ($suffix ?: 'stable');

        if ($normalizedChannel !== 'stable' && blank($suffix)) {
            $suffix = $normalizedChannel;
        }

        if ($normalizedChannel === 'stable' && $suffix === 'stable') {
            $suffix = null;
        }

        return new self(
            major: (int) $matches['major'],
            minor: (int) $matches['minor'],
            patch: (int) $matches['patch'],
            suffix: $suffix !== '' ? $suffix : null,
            channel: $normalizedChannel,
        );
    }

    public function incrementPatch(string $channel = 'stable'): self
    {
        return new self($this->major, $this->minor, $this->patch + 1, null, 'stable')
            ->forChannel($channel);
    }

    public function incrementMinor(string $channel = 'stable'): self
    {
        return new self($this->major, $this->minor + 1, 0, null, 'stable')
            ->forChannel($channel);
    }

    public function incrementMajor(string $channel = 'stable'): self
    {
        return new self($this->major + 1, 0, 0, null, 'stable')
            ->forChannel($channel);
    }

    public function forChannel(string $channel): self
    {
        $normalizedChannel = trim($channel) === '' ? 'stable' : trim($channel);

        return new self(
            major: $this->major,
            minor: $this->minor,
            patch: $this->patch,
            suffix: $normalizedChannel === 'stable' ? null : $normalizedChannel,
            channel: $normalizedChannel,
        );
    }

    public function label(): string
    {
        $base = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);

        return $this->suffix === null || $this->suffix === 'stable'
            ? $base
            : "{$base}-{$this->suffix}";
    }
}
