<?php
/**
 * Pandoc PHP
 *
 * Copyright (c) Ryan Kadwell <ryan@riaka.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pandoc;

/**
 * Naive wrapper for haskell's pandoc utility
 *
 * @author Ryan Kadwell <ryan@riaka.ca>
 */
class Pandoc
{
    /**
     * Where is the executable located
     * @var string
     */
    private $executable;

    /**
     * Where to take the content for pandoc from
     * @var string
     */
    private $tmpFile;

    /**
     * List of valid input types
     * @var array
     */
    private $inputFormats = array(
        "docbook",
        "html",
        "json",
        "latex",
        "markdown",
        "markdown_github",
        "markdown_mmd",
        "markdown_phpextra",
        "markdown_strict",
        "mediawiki",
        "native",
        "rst",
        "textile"
    );

    /**
     * List of valid string output types
     * @var array
     */
    private $stringOutputFormats = array(
        "asciidoc",
        "beamer",
        "context",
        "docbook",
        "dzslides",
        "fb2",
        "html",
        "html5",
        "json",
        "latex",
        "man",
        "markdown",
        "markdown_github",
        "markdown_mmd",
        "markdown_phpextra",
        "markdown_strict",
        "mediawiki",
        "native",
        "opendocument",
        "org",
        "plain",
        "rst",
        "rtf",
        "s5",
        "slideous",
        "slidy",
        "texinfo",
        "textile"
    );

	/**
	 * List of valid binary output formats
	 * @var array
	 */
	private $binaryOutputFormats = array(
		"docx",
		"epub",
		"epub3",
		"odt",
		"pdf"
	);

	/**
	 * List of all valid output formats
	 * @var array
	 */
	private $outputFormats = array();

    /**
     * Setup path to the pandoc binary
     *
     * @param string $executable Path to the pandoc executable
     */
    public function __construct($executable = null)
    {
        $this->tmpFile = sprintf(
            "%s/%s", sys_get_temp_dir(), uniqid("pandoc")
        );

		$this->outputFormats = array_merge($this->stringOutputFormats, $this->binaryOutputFormats);

        // Since we can not validate that the command that they give us is
        // *really* pandoc we will just check that its something.
        // If the provide no path to pandoc we will try to find it on our own
        if ( ! $executable) {
            exec('which pandoc', $output, $returnVar);
            if ($returnVar === 0) {
                $this->executable = $output[0];
            } else {
                throw new PandocException('Unable to locate pandoc');
            }
        } else {
            $this->executable = $executable;
        }

        if ( ! is_executable($this->executable)) {
            throw new PandocException('Pandoc executable is not executable');
        }
    }

    /**
     * Run the conversion from one type to another
     *
     * @param string $from The type we are converting from
     * @param string $to   The type we want to convert the document to
     *
     * @return string
     */
    public function convert($content, $from, $to)
    {
		$options = compact('from', 'to');
		$this->validateConversion($options);

		return $this->runWith($content, $options);
    }

    /**
     * Run the pandoc command with specific options.
     *
     * Provides more control over what happens. You simply pass an array of
     * key value pairs of the command options omitting the -- from the start.
     * If you want to pass a command that takes no argument you set its value
     * to null.
     *
     * @param string $content The content to run the command on
     * @param array  $options The options to use
     *
     * @return string The returned content
     */
    public function runWith($content, $options)
    {
		$this->validateConversion($options);

        $commandOptions = array();
        foreach ($options as $key => $value) {
            if ($key == 'to' && in_array($value, $this->binaryOutputFormats)) {
                $commandOptions[] = '-o '.$this->tmpFile;
            }

            if (null === $value) {
                $commandOptions[] = "--$key";
                continue;
            }

            $commandOptions[] = "--$key=$value";
        }

        file_put_contents($this->tmpFile, $content);

        $command = sprintf(
            "%s %s %s",
            $this->executable,
            implode(' ', $commandOptions),
            $this->tmpFile
        );

        exec($command, $output);

        if (in_array($options['to'], $this->binaryOutputFormats)) {
            return file_get_contents($this->tmpFile);
        } else {
            return implode("\n", $output);
        }
    }

    /**
     * Remove the temporary files that were created
     */
    public function __destruct()
    {
        if (file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    /**
     * Returns the pandoc version number
     *
     * @return string
     */
    public function getVersion()
    {
        exec(sprintf('%s --version', $this->executable), $output);

        return trim(str_replace('pandoc', '', $output[0]));
    }

	/**
	 * Throws an error if 'from' or 'to' formats are unrecognized
	 *
	 * @access private
	 * @param array $options The options to be validated
	 * @return void
	 */
	private function validateConversion($options)
	{
		$from = $options['from'];
		$to = $options['to'];

        if ( ! in_array($from, $this->inputFormats)) {
            throw new PandocException(
                sprintf('%s is not a valid input format for pandoc', $from)
            );
        }

        if ( ! in_array($to, $this->outputFormats)) {
            throw new PandocException(
                sprintf('%s is not a valid output format for pandoc', $to)
            );
        }
	}
}
