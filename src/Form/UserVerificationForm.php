<?php

namespace Drupal\email_verification\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Egulias\EmailValidator\EmailValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class UserVerificationForm.
 *
 * User Email functionality.
 */
class UserVerificationForm extends FormBase {

  /**
   * Email validator.
   *
   * @var \Egulias\EmailValidator\EmailValidator
   */
  protected $emailValidator;

  /**
   * The token replacement instance.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger instance.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * An Account instance.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_verification_form';
  }

  /**
   * Constructs a UserVerificationForm instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Egulias\EmailValidator\EmailValidator $emailValidator
   *   The Email Validator service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement instance.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The Mail service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger Service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   An Account instance.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              EmailValidator $emailValidator,
                              Token $token,
                              MailManagerInterface $mailManager,
                              MessengerInterface $messenger,
                              LoggerChannelFactoryInterface $loggerFactory,
                              AccountInterface $account
  ) {
    $this->configFactory = $configFactory;
    $this->emailValidator = $emailValidator;
    $this->token = $token;
    $this->mailManager = $mailManager;
    $this->messenger = $messenger;
    $this->logger = $loggerFactory->get('email_verification');
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('email.validator'),
      $container->get('token'),
      $container->get('plugin.manager.mail'),
      $container->get('messenger'),
      $container->get('logger.factory'),
      $container->get('current_user')
    );
    // https://www.drupal.org/docs/8/api/logging-api/overview
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('email_verification.settings');
    $helpText = $config->get('user_email_verification_helptext.value');
    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail address'),
      '#description' => $this->t('Validate Email address before creating account.'),
      '#weight' => '0',

    ];

    if (!empty($helpText)) {
      $form['info'] = [
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#markup' => $helpText,
        '#suffix' => '</p>',
      ];
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#name' => 'submit',
      '#value' => $this->t('Send Email'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Go Back'),
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if ($button_clicked == 'reset') {
      $targetUrl = Url::fromRoute('user.login')->toString();
      $response = new RedirectResponse($targetUrl, 301);
      $response->send();
      exit;
    }
    $values = $form_state->getValues();
    if (empty($values['mail'])) {
      $form_state->setError($form['mail'], $this->t('Please provide email address.'));
    }
    foreach ($values as $key => $value) {
      if ($key == 'mail' && !empty($value)) {
        if (!$this->emailValidator->isValid($value)) {
          $form_state->setError($form['mail'], $this->t('Please provide valid email address.'));
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if ($button_clicked == 'reset') {
      return;
    }
    $values = $form_state->getValues();
    if (empty($values['mail'])) {
      return;
    }
    $email = $values['mail'];
    $config = $this->configFactory->getEditable('email_verification.settings');
    $salt = $config->get('user_email_verification_salt') ?? 'email';
    $emailKey = md5($salt . $email);
    global $base_url;
    // Get template.
    $msgTpl = $config->get('user_email_verification_tpl');
    // Prepare verification link.
    $email2 = urlencode($email);
    $registerUrl = Url::fromRoute('user.register')->toString();
    $link = "{$base_url}{$registerUrl}?email={$email2}&verify=" . $emailKey;
    // Replace token.
    $msgTpl = $this->token->replace($msgTpl,
      ['emailtoverify' => $email, 'emailverificationlink' => $link]);
    $msgTpl = str_replace('&amp;', '&', $msgTpl);
    $module = 'email_verification';
    $this->logger->info(print_r($msgTpl, TRUE));
    // Send Email.
    // Replace with Your key.
    $key = 'email_verification';
    $to = $email;
    $langcode = $this->account->getPreferredLangcode();
    $send = TRUE;
    $params['message'] = $msgTpl;
    $params['title'] = $this->t('User Email Verification');
    $params['from'] = $this->configFactory->get('system.site')->get('mail');
    $params['subject'] = $this->t('User Email Verification');
    $this->logger->info('To :' . $to);
    $params['body'][] = Html::escape($params['message']);

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] != TRUE) {
      $message =
        $this->t('There was a problem sending your email notification to @email.',
          ['@email' => $to]);
      $this->logger->error($message);
      $this->messenger->addMessage($message, MessengerInterface::TYPE_ERROR);

      return;
    }

    $message = $this->t('We have sent an email for verification to @email', ['@email' => $to]);
    $this->logger->info($message);
    $this->messenger->addMessage($message, MessengerInterface::TYPE_STATUS);
  }

}
