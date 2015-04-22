<?php
/**
 * Parses and verifies the doc comments for functions.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

if (class_exists('PEAR_Sniffs_Commenting_FunctionCommentSniff', true) === false) {
    throw new PHP_CodeSniffer_Exception('Class PEAR_Sniffs_Commenting_FunctionCommentSniff not found');
}

/**
 * Parses and verifies the doc comments for functions.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class Ongr_Sniffs_Commenting_FunctionCommentSniff extends PEAR_Sniffs_Commenting_FunctionCommentSniff
{

    /**
     * Phpcs file.
     */
    private $phpcsFile;

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->phpcsFile = $phpcsFile;
        $tokens = $phpcsFile->getTokens();
        $find   = PHP_CodeSniffer_Tokens::$methodPrefixes;
        $find[] = T_WHITESPACE;


        $commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1), null, true);
        if ($tokens[$commentEnd]['code'] === T_COMMENT) {
            // Inline comments might just be closing comments for
            // control structures or functions instead of function comments
            // using the wrong comment type. If there is other code on the line,
            // assume they relate to that code.
            $prev = $phpcsFile->findPrevious($find, ($commentEnd - 1), null, true);
            if ($prev !== false && $tokens[$prev]['line'] === $tokens[$commentEnd]['line']) {
                $commentEnd = $prev;
            }
        }

        if ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG
            && $tokens[$commentEnd]['code'] !== T_COMMENT
        ) {
            $phpcsFile->addError('Missing function doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'Function has doc comment', 'no');
            return;
        } else {
            $phpcsFile->recordMetric($stackPtr, 'Function has doc comment', 'yes');
        }

        if ($tokens[$commentEnd]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a function comment', $stackPtr, 'WrongStyle');
            return;
        }

        if ($tokens[$commentEnd]['line'] !== ($tokens[$stackPtr]['line'] - 1)) {
            $error = 'There must be no blank lines after the function comment';
            $phpcsFile->addError($error, $commentEnd, 'SpacingAfter');
        }

        $commentStart = $tokens[$commentEnd]['comment_opener'];
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@see') {
                // Make sure the tag isn't empty.
                $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
                if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                    $error = 'Content missing for @see tag in function comment';
                    $phpcsFile->addError($error, $tag, 'EmptySees');
                }
            }
            // Ongr checks inheritdoc comment.
            if ($tokens[$tag]['content'] === '@inheritdoc') {
                $error = 'You must use {@inheritdoc}';
                $fix = $phpcsFile->addFixableError($error, $tag, 'WrongInheritDocStyle');
                if ($fix === true) {
                    $phpcsFile->fixer->replaceToken($tag, '{@inheritdoc}');
                }
                return;
            }

        }
        $this->processReturn($phpcsFile, $stackPtr, $commentStart);
        $this->processThrows($phpcsFile, $stackPtr, $commentStart);
        $this->processDeprecated($phpcsFile, $stackPtr, $commentStart);
        $this->processParams($phpcsFile, $stackPtr, $commentStart);
        $this->processComments($phpcsFile, $stackPtr, $commentStart);

    }//end process()

    /**
     * @param PHP_CodeSniffer_File $phpcsFile
     * @param                      $stackPtr
     * @param                      $commentStart
     *
     * @throws PHP_CodeSniffer_Exception
     */
    protected function processComments(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();
        $empty = array(
            T_DOC_COMMENT_WHITESPACE,
            T_DOC_COMMENT_STAR,
        );

        $find = [
            T_DOC_COMMENT_CLOSE_TAG,
            T_DOC_COMMENT_TAG,
        ];

        $commentEnd = $phpcsFile->findNext($find, $commentStart);

        $methodName      = $phpcsFile->getDeclarationName($stackPtr);
        $short = $phpcsFile->findNext($empty, ($commentStart + 1), $commentEnd, true);
        $shortEnd     = $short;
        $shortContent = $tokens[$short]['content'];
        $lastChar = $shortContent[(strlen($shortContent) - 1)];

        // Check for a comment description.
        if (trim($short) === '') {
            if (preg_match('/^(set|get|has|add|is)[A-Z]|__construct/', $methodName) !== 1) {
                $error = 'Missing short description in function doc comment';
                $phpcsFile->addError($error, $commentStart, 'MissingShort');
            }
            return;
        }
        if (preg_match('#^(\p{Lu}|{@inheritdoc})#u', $shortContent) === 0) {
            $error = 'Function comment short description must start with a capital letter';
            $phpcsFile->addError($error, ($commentStart + 1), 'ShortNotCapital');
        }
        if (preg_match('#{@inheritdoc}$#u', $shortContent) === 0 && $lastChar !== '.') {
            $error = 'Function comment short description must end with a full stop';
            $phpcsFile->addError($error, ($commentStart + 1), 'ShortFullStop');
        }

        //Check whitespaces beetween '*' and short comment.
        $foundShort = strlen($tokens[$short-1]['content']);
        if ($foundShort > 1) {
            $phpcsFile->addError(
                "Expected one whitespace before short description. Found {$foundShort}.",
                $short,
                'ExtraWhitespace'
            );
        }

        $long = $phpcsFile->findNext($empty, ($shortEnd + 1), ($commentEnd - 1), true);
        if ($long === false) {
            return;
        }

        //Check whitespaces beetween '*' and long comment.
        $foundLong = strlen($tokens[$long-1]['content']);
        if ($foundLong > 1) {
            $phpcsFile->addError(
                "Expected one whitespace before long description. Found {$foundLong}.",
                $long,
                'ExtraWhitespace'
            );
        }

        if ($tokens[$long]['code'] === T_DOC_COMMENT_STRING) {
            if ($tokens[$long]['line'] !== ($tokens[$shortEnd]['line'] + 2)) {
                $error = 'There must be exactly one blank line between descriptions in a doc comment';
                $phpcsFile->addError($error, $long, 'SpacingBetween');
            }

            if (preg_match('/\p{Lu}|\P{L}/u', $tokens[$long]['content'][0]) === 0) {
                $error = 'Doc comment long description must start with a capital letter';
                $phpcsFile->addError($error, $long, 'LongNotCapital');
            }
        }//end if
    }

    protected function isLineAboveEmpty($tag, $tokens, $commentStart)
    {
        $lineAbove = $tokens[$tag - 5];
        $content = $tokens[$tag]['content'];
        $error = 'Line above ' . $content . ' must be empty';
        $sameContent = false;
        for ($x = $tag-1; $x >= $commentStart; $x--) {
            if ($tokens[$x]['content'] == $content) {
                $this->isLineAboveNotEmpty($lineAbove, $content, $tag - 5);
                $sameContent = true;
                break;
            }
        }
        if ($lineAbove['code'] !== T_DOC_COMMENT_STAR && $sameContent == false) {
            if ($lineAbove['code'] === T_DOC_COMMENT_OPEN_TAG) {
                return;
            }
            $this->phpcsFile->addError($error, $tag - 5, 'LineAboveNotEmpty');
        }
    }

    protected function isLineAboveNotEmpty($lineAbove, $content, $line)
    {
        $error = 'Line above ' . $content . ' must not be empty';
        if ($lineAbove['code'] === T_DOC_COMMENT_STAR ) {
            $this->phpcsFile->addError($error, $line, 'LineAboveIsEmpty');
        }
    }

    /**
     * Process the return comment of this function comment.
     *
     * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processReturn(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip constructor and destructor.
        $methodName      = $phpcsFile->getDeclarationName($stackPtr);
        $isSpecialMethod = ($methodName === '__construct' || $methodName === '__destruct');

        $return = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                if ($return !== null) {
                    $error = 'Only 1 @return tag is allowed in a function comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateReturn');
                    return;
                }
                $return = $tag;
                $this->isLineAboveEmpty($tag, $tokens, $commentStart);
            }
        }

        if ($isSpecialMethod === true) {
            return;
        }


        if ($return !== null) {
            $content = $tokens[($return + 2)]['content'];
            if (empty($content) === true || $tokens[($return + 2)]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Return type missing for @return tag in function comment';
                $phpcsFile->addError($error, $return, 'MissingReturnType');
            } else {
                // Check return type (can be multiple, separated by '|').
                $typeNames      = explode('|', $content);
                $suggestedNames = array();
                foreach ($typeNames as $i => $typeName) {
                    $suggestedName = PHP_CodeSniffer::suggestType($typeName);
                    if ($suggestedName === 'boolean') {
                        $suggestedName = 'bool';
                    } elseif ($suggestedName === 'integer') {
                        $suggestedName = 'int';
                    }
                    if (in_array($suggestedName, $suggestedNames) === false) {
                        $suggestedNames[] = $suggestedName;
                    }
                }
                $suggestedType = implode('|', $suggestedNames);
                if ($content !== $suggestedType) {
                    $error = 'Function return type "%s" is invalid';
                    $data = [$content];
                    $phpcsFile->addError($error, $return, 'InvalidReturn', $data);
                }

                // If the return type is void, make sure there is
                // no return statement in the function.
                if ($content === 'void') {
                    if (isset($tokens[$stackPtr]['scope_closer']) === true) {
                        $endToken = $tokens[$stackPtr]['scope_closer'];
                        for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
                            if ($tokens[$returnToken]['code'] === T_CLOSURE) {
                                $returnToken = $tokens[$returnToken]['scope_closer'];
                                continue;
                            }

                            if ($tokens[$returnToken]['code'] === T_RETURN) {
                                break;
                            }
                        }

                        if ($returnToken !== $endToken) {
                            // If the function is not returning anything, just
                            // exiting, then there is no problem.
                            $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
                            if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
                                $error = 'Function return type is void, but function contains return statement';
                                $phpcsFile->addError($error, $return, 'InvalidReturnVoid');
                            }
                        }
                    }//end if
                } else if ($content !== 'mixed') {
                    // If return type is not void, there needs to be a return statement
                    // somewhere in the function that returns something.
                    if (isset($tokens[$stackPtr]['scope_closer']) === true) {
                        $endToken    = $tokens[$stackPtr]['scope_closer'];
                        $returnToken = $phpcsFile->findNext(T_RETURN, $stackPtr, $endToken);
                        if ($returnToken === false) {
                            $error = 'Function return type is not void, but function has no return statement';
                            $phpcsFile->addError($error, $return, 'InvalidNoReturn');
                        } else {
                            $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
                            if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
                                $error = 'Function return type is not void, but function is returning void here';
                                $phpcsFile->addError($error, $returnToken, 'InvalidReturnNotVoid');
                            }
                        }
                    }
                }//end if

                $comment = null;
                if ($tokens[($return + 2)]['code'] === T_DOC_COMMENT_STRING) {
                    $matches = array();
                    preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[($return + 2)]['content'], $matches);
                    if (isset($matches[2]) === true) {
                        $comment = $matches[2];
                    }
                }

                //ONGR Validate that return comment begins with capital letter and ends with full stop.
                if ($comment !== null) {
                    $firstChar = $comment{0};
                    if (preg_match('|\p{Lu}|u', $firstChar) === 0) {
                        $error = 'Return comment must start with a capital letter';
                        $fix = $phpcsFile->addFixableError($error, ($return + 2), 'ReturnCommentNotCapital');

                        if ($fix === true) {
                            $newComment = ucfirst($comment);
                            $tokenLength = strlen($tokens[($return + 2)]['content']);
                            $commentLength = strlen($comment);
                            $tokenWithoutComment
                                = substr($tokens[($return + 2)]['content'], 0, $tokenLength - $commentLength);

                            $phpcsFile->fixer->replaceToken(($return + 2), $tokenWithoutComment . $newComment);
                        }
                    }

                    $lastChar = substr($comment, -1);
                    if ($lastChar !== '.') {
                        $error = 'Return comment must end with a full stop';
                        $fix = $phpcsFile->addFixableError($error, ($return + 2), 'ReturnCommentFullStop');

                        if ($fix === true) {
                            $phpcsFile->fixer->addContent(($return + 2), '.');
                        }
                    }
                }
            }//end if
        } else {
            //ONGR Return is not necessary.
//            $error = 'Missing @return tag in function comment';
//            $phpcsFile->addError($error, $tokens[$commentStart]['comment_closer'], 'MissingReturn');
        }//end if

    }//end processReturn()


    /**
     * Process any throw tags that this function comment has.
     *
     * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processThrows(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $throws = array();
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@throws') {
                continue;
            }

            $this->isLineAboveEmpty($tag, $tokens, $commentStart);
            $exception = null;
            $comment   = null;
            if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
                $matches = array();
                preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[($tag + 2)]['content'], $matches);
                $exception = $matches[1];
                if (isset($matches[2]) === true) {
                    $comment = $matches[2];
                }
            }

            if ($exception === null) {
                $error = 'Exception type and comment missing for @throws tag in function comment';
                $phpcsFile->addError($error, $tag, 'InvalidThrows');
            } else if ($comment === null) {
                //ONGR We dont write throw comments
//                $error = 'Comment missing for @throws tag in function comment';
//                $phpcsFile->addError($error, $tag, 'EmptyThrows');
            } else {
                // Any strings until the next tag belong to this comment.
                if (isset($tokens[$commentStart]['comment_tags'][($pos + 1)]) === true) {
                    $end = $tokens[$commentStart]['comment_tags'][($pos + 1)];
                } else {
                    $end = $tokens[$commentStart]['comment_closer'];
                }

                for ($i = ($tag + 3); $i < $end; $i++) {
                    if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                        $comment .= ' '.$tokens[$i]['content'];
                    }
                }

                // Starts with a capital letter and ends with a fullstop.
                $firstChar = $comment{0};
                if (strtoupper($firstChar) !== $firstChar) {
                    $error = '@throws tag comment must start with a capital letter';
                    $phpcsFile->addError($error, ($tag + 2), 'ThrowsNotCapital');
                }

                $lastChar = substr($comment, -1);
                if ($lastChar !== '.') {
                    $error = '@throws tag comment must end with a full stop';
                    $phpcsFile->addError($error, ($tag + 2), 'ThrowsNoFullStop');
                }
            }//end if
        }//end foreach

    }//end processThrows()

    /**
     * Process any deprecated tags that this function comment has.
     *
     * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processDeprecated(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@deprecated') {
                continue;
            }

            $content = $tokens[($tag + 2)]['content'];
            if (empty($content) === true || $tokens[($tag + 2)]['code'] !== T_DOC_COMMENT_STRING) {
                $error = 'Deprecated type missing for @deprecated tag in function comment';
                $phpcsFile->addError($error, $tag, 'MissingDeprecatedType');
            } else {
                //ONGR Validate that return comment begins with capital letter and ends with full stop.
                if ($content !== null) {
                    $firstChar = $content{0};
                    if (preg_match('|\p{Lu}|u', $firstChar) === 0) {
                        $error = 'Deprecated comment must start with a capital letter';
                        $fix = $phpcsFile->addFixableError($error, ($tag + 2), 'DeprecatedCommentNotCapital');

                        if ($fix === true) {
                            $newComment = ucfirst($content);
                            $tokenLength = strlen($tokens[($tag + 2)]['content']);
                            $commentLength = strlen($content);
                            $tokenWithoutComment
                                = substr($tokens[($tag + 2)]['content'], 0, $tokenLength - $commentLength);

                            $phpcsFile->fixer->replaceToken(($tag + 2), $tokenWithoutComment . $newComment);
                        }
                    }

                    $lastChar = substr($content, -1);
                    if ($lastChar !== '.') {
                        $error = 'Deprecated comment must end with a full stop';
                        $fix = $phpcsFile->addFixableError($error, ($tag + 2), 'DeprecatedCommentFullStop');

                        if ($fix === true) {
                            $phpcsFile->fixer->addContent(($tag + 2), '.');
                        }
                    }
                }
            }
        }
    }

    /**
     * Process the function parameter comments.
     *
     * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processParams(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        $params  = array();
        $maxType = 0;
        $maxVar  = 0;
        foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
            if ($tokens[$tag]['content'] !== '@param') {
                continue;
            }

            $this->isLineAboveEmpty($tag, $tokens, $commentStart);
            $type         = '';
            $typeSpace    = 0;
            $var          = '';
            $varSpace     = 0;
            $comment      = '';
            $commentLines = array();
            if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
                $matches = array();
                preg_match('/([^$&]+)(?:((?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/', $tokens[($tag + 2)]['content'], $matches);

                $typeLen   = strlen($matches[1]);
                $type      = trim($matches[1]);
                $typeSpace = ($typeLen - strlen($type));
                $typeLen   = strlen($type);
                if ($typeLen > $maxType) {
                    $maxType = $typeLen;
                }

                if (isset($matches[2]) === true) {
                    $var    = $matches[2];
                    $varLen = strlen($var);
                    if ($varLen > $maxVar) {
                        $maxVar = $varLen;
                    }

                    if (isset($matches[4]) === true) {
                        $varSpace       = strlen($matches[3]);
                        $comment        = $matches[4];
                        $commentLines[] = array(
                            'comment' => $comment,
                            'token'   => ($tag + 2),
                            'indent'  => $varSpace,
                        );

                        // Any strings until the next tag belong to this comment.
                        if (isset($tokens[$commentStart]['comment_tags'][($pos + 1)]) === true) {
                            $end = $tokens[$commentStart]['comment_tags'][($pos + 1)];
                        } else {
                            $end = $tokens[$commentStart]['comment_closer'];
                        }

                        for ($i = ($tag + 3); $i < $end; $i++) {
                            if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
                                $indent = 0;
                                if ($tokens[($i - 1)]['code'] === T_DOC_COMMENT_WHITESPACE) {
                                    $indent = strlen($tokens[($i - 1)]['content']);
                                }

                                $comment       .= ' '.$tokens[$i]['content'];
                                $commentLines[] = array(
                                    'comment' => $tokens[$i]['content'],
                                    'token'   => $i,
                                    'indent'  => $indent,
                                );
                            }
                        }
                    } else {
//                        $error = 'Missing parameter comment';
//                        $phpcsFile->addError($error, $tag, 'MissingParamComment');
//                        $commentLines[] = array('comment' => '');
                    }//end if
                } else {
                    $error = 'Missing parameter name';
                    $phpcsFile->addError($error, $tag, 'MissingParamName');
                }//end if
            } else {
                $error = 'Missing parameter type';
                $phpcsFile->addError($error, $tag, 'MissingParamType');
            }//end if

            $params[] = array(
                'tag'          => $tag,
                'type'         => $type,
                'var'          => $var,
                'comment'      => $comment,
                'commentLines' => $commentLines,
                'type_space'   => $typeSpace,
                'var_space'    => $varSpace,
            );
        }//end foreach

        $realParams  = $phpcsFile->getMethodParameters($stackPtr);
        $foundParams = array();

        foreach ($params as $pos => $param) {
            // If the type is empty, the whole line is empty.
            if ($param['type'] === '') {
                continue;
            }

            // Check the param type value.
            $typeNames = explode('|', $param['type']);
            foreach ($typeNames as $typeName) {
                $suggestedName = PHP_CodeSniffer::suggestType($typeName);
                if ($suggestedName === 'boolean') {
                    $suggestedName = 'bool';
                } elseif ($suggestedName === 'integer') {
                    $suggestedName = 'int';
                }
                if ($typeName !== $suggestedName) {
                    $error = 'Expected "%s" but found "%s" for parameter type';
                    $data  = array(
                        $suggestedName,
                        $typeName,
                    );

                    $fix = $phpcsFile->addFixableError($error, $param['tag'], 'IncorrectParamVarName', $data);
                    if ($fix === true) {
                        $content  = $suggestedName;
                        $content .= str_repeat(' ', $param['type_space']);
                        $content .= $param['var'];
                        $content .= str_repeat(' ', $param['var_space']);
                        if (isset($param['commentLines'][0]) === true) {
                            $content .= $param['commentLines'][0]['comment'];
                        }

                        $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);
                    }
                } else if (count($typeNames) === 1) {
                    // Check type hint for array and custom type.
                    $suggestedTypeHint = '';
                    if (strpos($suggestedName, 'array') !== false || strpos($suggestedName, '[]') !== false) {
                        $suggestedTypeHint = 'array';
                    } else
                    if (strpos($suggestedName, 'callable') !== false) {
                        $suggestedTypeHint = 'callable';
                    } else if (in_array($typeName, PHP_CodeSniffer::$allowedTypes) === false) {
                        $suggestedTypeHint = $suggestedName;
                    }

                    if ($suggestedTypeHint !== '' && isset($realParams[$pos]) === true) {
                        $typeHint = $realParams[$pos]['type_hint'];
                        if ($typeHint !== '' && $typeHint !== substr($suggestedTypeHint, (strlen($typeHint) * -1))) {
                            $error = 'Expected type hint "%s"; found "%s" for %s';
                            $data  = array(
                                $suggestedTypeHint,
                                $typeHint,
                                $param['var'],
                            );
                            $phpcsFile->addError($error, $stackPtr, 'IncorrectTypeHint', $data);
                        }
                    } else if ($suggestedTypeHint === '' && isset($realParams[$pos]) === true) {
                        $typeHint = $realParams[$pos]['type_hint'];
                        if ($typeHint !== '') {
                            $error = 'Unknown type hint "%s" found for %s';
                            $data  = array(
                                $typeHint,
                                $param['var'],
                            );
                            $phpcsFile->addError($error, $stackPtr, 'InvalidTypeHint', $data);
                        }
                    }//end if
                }//end if
            }//end foreach

            if ($param['var'] === '') {
                continue;
            }

            $foundParams[] = $param['var'];

            // Check number of spaces after the type.
            $spaces = ($maxType - strlen($param['type']) + 1);
            if ($param['type_space'] !== $spaces) {
                $error = 'Expected %s spaces after parameter type; %s found';
                $data  = array(
                    $spaces,
                    $param['type_space'],
                );

                $fix = $phpcsFile->addFixableError($error, $param['tag'], 'SpacingAfterParamType', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->beginChangeset();

                    $content  = $param['type'];
                    $content .= str_repeat(' ', $spaces);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $param['var_space']);
                    $content .= $param['commentLines'][0]['comment'];
                    $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $lineNum => $line) {
                        if ($lineNum === 0
                            || $param['commentLines'][$lineNum]['indent'] === 0
                        ) {
                            continue;
                        }

                        $newIndent = ($param['commentLines'][$lineNum]['indent'] + $spaces - $param['type_space']);
                        $phpcsFile->fixer->replaceToken(
                            ($param['commentLines'][$lineNum]['token'] - 1),
                            str_repeat(' ', $newIndent)
                        );
                    }

                    $phpcsFile->fixer->endChangeset();
                }//end if
            }//end if

            // Make sure the param name is correct.
            if (isset($realParams[$pos]) === true) {
                $realName = $realParams[$pos]['name'];
                if ($realName !== $param['var']) {
                    $code = 'ParamNameNoMatch';
                    $data = array(
                        $param['var'],
                        $realName,
                    );

                    $error = 'Doc comment for parameter %s does not match ';
                    if (strtolower($param['var']) === strtolower($realName)) {
                        $error .= 'case of ';
                        $code   = 'ParamNameNoCaseMatch';
                    }

                    $error .= 'actual variable name %s';

                    $phpcsFile->addError($error, $param['tag'], $code, $data);
                }
            } else if (substr($param['var'], -4) !== ',...') {
                // We must have an extra parameter comment.
                $error = 'Superfluous parameter comment';
                $phpcsFile->addError($error, $param['tag'], 'ExtraParamComment');
            }//end if

            if ($param['comment'] === '') {
                continue;
            }

            // Check number of spaces after the var name.
            $spaces = ($maxVar - strlen($param['var']) + 1);
            if ($param['var_space'] !== $spaces) {
                $error = 'Expected %s spaces after parameter name; %s found';
                $data  = array(
                    $spaces,
                    $param['var_space'],
                );

                $fix = $phpcsFile->addFixableError($error, $param['tag'], '
                ', $data);
                if ($fix === true) {
                    $phpcsFile->fixer->beginChangeset();

                    $content  = $param['type'];
                    $content .= str_repeat(' ', $param['type_space']);
                    $content .= $param['var'];
                    $content .= str_repeat(' ', $spaces);
                    $content .= $param['commentLines'][0]['comment'];
                    $phpcsFile->fixer->replaceToken(($param['tag'] + 2), $content);

                    // Fix up the indent of additional comment lines.
                    foreach ($param['commentLines'] as $lineNum => $line) {
                        if ($lineNum === 0
                            || $param['commentLines'][$lineNum]['indent'] === 0
                        ) {
                            continue;
                        }

                        $newIndent = ($param['commentLines'][$lineNum]['indent'] + $spaces - $param['var_space']);
                        $phpcsFile->fixer->replaceToken(
                            ($param['commentLines'][$lineNum]['token'] - 1),
                            str_repeat(' ', $newIndent)
                        );
                    }

                    $phpcsFile->fixer->endChangeset();
                }//end if
            }//end if

            // Param comments must start with a capital letter and end with the full stop.
            $firstChar = $param['comment']{0};
            if (preg_match('|\p{Lu}|u', $firstChar) === 0) {
                $error = 'Parameter comment must start with a capital letter';
                $phpcsFile->addError($error, $param['tag'], 'ParamCommentNotCapital');
            }

            $lastChar = substr($param['comment'], -1);
            if ($lastChar !== '.') {
                $error = 'Parameter comment must end with a full stop';
                $phpcsFile->addError($error, $param['tag'], 'ParamCommentFullStop');
            }
        }//end foreach

        //ONGR Ignore doc tag rules if it contains only {@inheritdoc}.
        $inheritDocMatches = false;
        for ($i = $commentStart; $i < $tokens[$commentStart]['comment_closer']; $i++) {
            if ($tokens[$i]['content'] === '{@inheritdoc}') {
                $inheritDocMatches = true;
            }
        }
        // Report missing comments.
        if (!$inheritDocMatches) {
            $realNames = array();
            foreach ($realParams as $realParam) {
                $realNames[] = $realParam['name'];
            }

            $diff = array_diff($realNames, $foundParams);
            foreach ($diff as $neededParam) {
                $error = 'Doc comment for parameter "%s" missing';
                $data  = array($neededParam);
                $phpcsFile->addError($error, $commentStart, 'MissingParamTag', $data);
            }
        }


    }//end processParams()

}//end class