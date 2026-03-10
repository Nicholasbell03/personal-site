<?php

namespace App\Contracts;

interface DownstreamPostable
{
    public function getDownstreamUrl(): string;

    public function getDownstreamTitle(): string;

    public function getDownstreamDescription(): string;

    public function getDownstreamImageUrl(): ?string;
}
