<?php

/**
 * @file
 * Contains email_verification.tokens.inc.
 */

/**
 * Implements hook_token_info().
 */
function email_verification_token_info() {

  $types['user'] = [
    'name' => t('Users'),
    'description' => t('Tokens related to individual user accounts.'),
    'needs-data' => 'user',
  ];
  $user_email_verification['emailverificationlink'] = [
    'name' => t("Email Verification link"),
    'description' => t("Email Verification link"),
  ];
  $user_email_verification['emailtoverify'] = [
    'name' => t("Email Address"),
    'description' => t("Email Address"),
  ];

  return [
    'types' => $types,
    'tokens' => ['user' => $user_email_verification],
  ];
}

/**
 * Implements hook_tokens().
 */
function email_verification_tokens($type,
                                   $tokens,
                                   array $data = [],
                                   array $options = []) {
  $replacements = [];
  if ($type == 'user') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'emailverificationlink':
          $replacements[$original] = $data[$name];
          break;

        case 'emailtoverify':
          $replacements[$original] = $data[$name];
          break;
      }
    }
  }

  return $replacements;
}
