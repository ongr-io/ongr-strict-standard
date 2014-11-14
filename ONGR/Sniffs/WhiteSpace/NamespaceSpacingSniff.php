<?php

/**
 * Class to validate right amount of whitespaces after namespace declaration.
 */
class ONGR_Sniffs_WhiteSpace_NamespaceSpacingSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return [T_NAMESPACE];
    }

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
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener'])) {
            // Skip bracketed namespaces.
            return;
        }
        $content = $phpcsFile->findNext(T_SEMICOLON, $stackPtr) + 1;

        while ($tokens[$content]['code'] == T_WHITESPACE) {
            ++$content;
        }
        if (($tokens[$content]['line'] - $tokens[$stackPtr]['line']) > 2) {
            // Case for minimum lines is written somewhere else.
            $phpcsFile->addError(
                'There must be exactly 1 blank line after namespace declaration',
                $stackPtr,
                'NamespaceSpacing'
            );
        }
    }
}
