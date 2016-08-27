<?php
/**
 *
 * @author Glenn
 */
interface IConfig {
    /**
     * Generate the configuration
     */
    public function generate();
    /**
     * Validate the options, return array of errors if failed
     */
    public function validateOpts();
}
