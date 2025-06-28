<?php

namespace M2code\FileManager\Application\Image\Actions;

interface ImageAction
{
    public function execute($file): string;
}