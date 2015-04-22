<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ongr\Tests\Unit\Commenting;

use Ongr\Tests\AbstractSniffUnitTest;

/**
 * FunctionCommentSniff class.
 */
class FunctionCommentSniffTest extends AbstractSniffUnitTest
{
    /**
     * {@inheritdoc}
     */
    protected function getErrorList()
    {
        #TODO Should be fixed to disallow white spacing
        return [
//             End of line whitespaces are checked by Squiz.WhiteSpace.SuperfluousWhitespace.EndLine sniff.
//             We do not overwrite this sniff.
//            20 => ['Whitespace found at end of line'],

            34 => 1,
            36 => 1,
            68 => 1,
            89 => 1,
            99 => 1,
            109 => 2,
            117 => 1,
            129 => 1,
            131 => 1,
            136 => 1,
            153 => 2,
            175 => 1
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
