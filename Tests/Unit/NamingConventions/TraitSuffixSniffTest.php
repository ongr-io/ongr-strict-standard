<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\Tests\Unit\NamingConventions;

use ONGR\Tests\AbstractSniffUnitTest;

/**
 * TraitSuffixSniffTest class.
 */
class TraitSuffixSniffTest extends AbstractSniffUnitTest
{
    /**
     * {@inheritdoc}
     */
    protected function getErrorList()
    {
        return [
            5 => ['Trait name is not suffixed with "Trait"'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getWarningList()
    {
        return [];
    }
}
