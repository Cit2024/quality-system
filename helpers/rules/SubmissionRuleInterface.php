<?php

interface SubmissionRuleInterface {
    /**
     * Execute the rule logic.
     *
     * @param array $data The current submission data (metadata, answers, etc.)
     * @param array $context Contextual information (db connection, form details, etc.)
     * @param array $config Configuration specific to this rule instance
     * @return array Modified data (e.g., enriched metadata) or original data
     * @throws Exception If validation fails or rule execution errors
     */
    public function execute(array $data, array $context, array $config): array;
}
