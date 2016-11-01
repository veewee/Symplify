<?php

declare(strict_types=1);

/*
 * This file is part of Symplify
 * Copyright (c) 2016 Tomas Votruba (http://tomasvotruba.cz).
 */

namespace Symplify\PHP7_Sculpin\Renderable\Routing\Route;

use Symplify\PHP7_Sculpin\Configuration\Configuration;
use Symplify\PHP7_Sculpin\Contract\Renderable\Routing\Route\RouteInterface;
use Symplify\PHP7_Sculpin\Renderable\File\AbstractFile;
use Symplify\PHP7_Sculpin\Renderable\File\PostFile;
use Symplify\PHP7_Sculpin\Utils\PathNormalizer;

final class PostRoute implements RouteInterface
{
    /**
     * @var Configuration
     */
    private $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function matches(AbstractFile $file) : bool
    {
        return $file instanceof PostFile;
    }

    /**
     * @param PostFile $file
     */
    public function buildOutputPath(AbstractFile $file) : string
    {
        return PathNormalizer::normalize($this->buildRelativeUrl($file) . '/index.html');
    }

    /**
     * @param PostFile $file
     */
    public function buildRelativeUrl(AbstractFile $file) : string
    {
        $permalink = $this->configuration->getPostRoute();
        $permalink = preg_replace('/:year/', $file->getDateInFormat('Y'), $permalink);
        $permalink = preg_replace('/:month/', $file->getDateInFormat('m'), $permalink);
        $permalink = preg_replace('/:day/', $file->getDateInFormat('d'), $permalink);

        return preg_replace('/:title/', $file->getFilenameWithoutDate(), $permalink);
    }
}