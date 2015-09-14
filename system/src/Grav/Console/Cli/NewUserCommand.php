<?php
namespace Grav\Console\Cli;

use Grav\Common\Data\Blueprints;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use Grav\Common\User\User;
use Grav\Console\ConsoleTrait;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class CleanCommand
 * @package Grav\Console\Cli
 */
class NewUserCommand extends Command
{
    use ConsoleTrait;

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName("newuser")
            ->setDescription("Creates a new user")
            ->setHelp('The <info>newuser</info> creates a new user file in user/accounts/ folder');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setupConsole($input, $output);
        $helper = $this->getHelper('question');
        $data = [];

        $this->output->writeln('<green>Create new user</green>');
        $this->output->writeln('');

        // Get username and validate
        $question = new Question('Enter a <yellow>username</yellow>: ', 'admin');
        $question->setValidator(function ($value) {
            if (!preg_match('/^[a-z0-9_-]{3,16}$/', $value)) {
                throw new RuntimeException(
                    'Username should be between 3 and 16 characters, including lowercase letters, numbers, underscores, and hyphens. Uppercase letters, spaces, and special characters are not allowed'
                );
            }
            if (file_exists(self::getGrav()['locator']->findResource('user://accounts/' . $value . YAML_EXT))) {
                throw new RuntimeException(
                    'Username "'.$value.'" already exists, please pick another username'
                );
            }
            return $value;
        });
        $username = $helper->ask($this->input, $this->output, $question);

        // Get password and validate
        $password = $this->askForPassword($helper, 'Enter a <yellow>password</yellow>: ', function ($password1) use ($helper) {
            if (!preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/', $password1)) {
                throw new RuntimeException('Password must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters');
            }
            // Since input is hidden when prompting for passwords, the user is asked to repeat the password
            return $this->askForPassword($helper, 'Repeat the <yellow>password</yellow>: ', function ($password2) use ($password1) {
                if (strcmp($password2, $password1)) {
                    throw new RuntimeException('Passwords did not match.');
                }
                return $password2;
            });
        });
        $data['password'] = $password;

        // Get email and validate
        $question = new Question('Enter an <yellow>email</yellow>:   ');
        $question->setValidator(function ($value) {
            if (!preg_match('/^([a-z0-9_\.-]+)@([\da-z\.-]+)\.([a-z\.]{2,6})$/', $value)) {
                throw new RuntimeException(
                    'Not a valid email address'
                );
            }
            return $value;
        });
        $data['email'] = $helper->ask($this->input, $this->output, $question);

        // Choose permissions
        $question = new ChoiceQuestion(
            'Please choose a set of <yellow>permissions</yellow>:',
            array('a'=>'admin access', 's'=>'site access', 'b'=>'admin and site access'),
            'a'
        );
        $question->setErrorMessage('permissions %s is invalid.');
        $permissions_choice = $helper->ask($this->input, $this->output, $question);

        switch ($permissions_choice) {
            case 'a':
                $data['access']['admin'] = ['login' => true, 'super' => true];
                break;
            case 's':
                $data['access']['site'] = ['login' => true];
                break;
            case 'b':
                $data['access']['admin'] = ['login' => true, 'super' => true];
                $data['access']['site'] = ['login' => true];
        }

        // Get fullname
        $question = new Question('Enter a <yellow>fullname</yellow>: ');
        $question->setValidator(function ($value) {
            if ($value === null || trim($value) == '') {
                throw new RuntimeException(
                    'Fullname is required'
                );
            }
            return $value;
        });
        $data['fullname'] = $helper->ask($this->input, $this->output, $question);

        // Get title
        $question = new Question('Enter a <yellow>title</yellow>:    ');
        $data['title'] = $helper->ask($this->input, $this->output, $question);

        // Create user object and save it
        $user = new User($data);
        $file = CompiledYamlFile::instance(self::getGrav()['locator']->findResource('user://accounts/' . $username . YAML_EXT, true, true));
        $user->file($file);
        $user->save();

        $this->output->writeln('');
        $this->output->writeln('<green>Success!</green> User <cyan>'. $username .'</cyan> created.');
    }

    /**
     * Get password and validate.
     *
     * @param Helper    $helper
     * @param string    $question
     * @param callable  $validator
     *
     * @return string
     */
    protected function askForPassword(Helper $helper, $question, callable $validator)
    {
        $question = new Question($question);
        $question->setValidator($validator);
        $question->setHidden(true);
        $question->setHiddenFallback(true);
        return $helper->ask($this->input, $this->output, $question);
    }
}
