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
 * AbstractClassPrefixSniffTest class.
 */
class AbstractClassPrefixSniffTest extends AbstractSniffUnitTest
{
    /**
     * {@inheritdoc}
     */
    protected function getErrorList()
    {
        return [
            5 => ['Class name is not prefixed with "Abstract"'],
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
