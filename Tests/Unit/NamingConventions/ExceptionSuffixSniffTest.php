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
 * ExceptionSuffixSniffTest class.
 */
class ExceptionSuffixSniffTest extends AbstractSniffUnitTest
{
    /**
     * {@inheritdoc}
     */
    protected function getErrorList()
    {
        return [
            9 => ['Exception name is not suffixed with "Exception"'],
            17 => ['Exception name is not suffixed with "Exception"'],
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
