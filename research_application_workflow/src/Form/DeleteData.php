<?php

namespace Drupal\research_application_workflow\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class DeleteData extends FormBase {

    //this class provides a form to download application
 
    /**
     * {@inheritdoc}
     */
     public function getFormId() {
        return 'deletedata_form';
     }


    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface
    $form_state) {

        $end_date = \Drupal::config('research_application_workflow.applicationsettings')->get('end_date');
        $start_date = \Drupal::config('research_application_workflow.applicationsettings')->get('start_date');

        //display submit only if the applications end date is past
        if( (strtotime($end_date) < strtotime('now')) || (strtotime($start_date) > strtotime('now')) ) {
        //Return array of Form API elements
        $form['markup'] = [
            '#markup' => t('<p>This action will delete all non-administrator users and their content.</p>
                            <p>This action is permanent and <strong>cannot be reversed</strong>. You will <strong>lose all your data</strong>.</p>'),
            ];
        $form['confirm'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Type "delete" to proceed.'),
            '#default_value' => '', 
            '#size' => 20,
            '#maxlength' => 25,            
            '#required' => TRUE,
            ];
        $form['submit'] = [
            '#type' => 'submit',
            '#weight' => 1,
            '#value' => $this->t('Delete all data'),
        ];
        } else  {
            $form['markup'] = [
                '#markup' => t('<p>You cannot delete data.</p>
                                <p>Currently there is a Call for Applications process.<br>
                                Data can only be deleted after the current Call for Applications <a href="/admin/config/application-settings">end date</a></p>'),
                ];
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function validateForm(array &$form, FormStateInterface
    $form_state) {
        if ($form_state->getValue('confirm') !== 'delete') {
            $form_state->setErrorByName('email', t('Type "delete" to proceed.'));
        } else {
            // Define the roles to check.
            $roles_to_check = ['candidato', 'candidato_enviado', 'investigador_responsable', 'evaluador'];

            // Load users with the specified roles.
            $user_storage = \Drupal::entityTypeManager()->getStorage('user');

            // Query to get all user IDs with any of the specified roles.
            $uids_with_roles = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition('roles', $roles_to_check, 'IN')
            ->execute();

            if (empty($uids_with_roles)) {
            // If no users have the specified roles, add a validation error.
            $form_state->setErrorByName('submit', $this->t('No users to be deleted exist.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     * 
     */
    public function submitForm(array &$form, FormStateInterface
    $form_state) {
            $this->loadAllUsers();
            $this->DeleteAdminNodes();
            $this->deletePrivateDirectoriesAndZipFiles();
    }

    /**
     * Deletes users with specific roles, all their content, and all files owned by them.
     *
     * @param int $uid
     *   The user ID of the account to delete.
     */
    function delete_user_with_specific_roles($uid) {
        // Define the roles for which users should be deleted.
        $roles_to_check = ['candidato', 'candidato_enviado', 'investigador_responsable', 'evaluador'];
    
        // Load the user account.
        $user = User::load($uid);
        if (!$user) {
        \Drupal::messenger()->addMessage(t('User not found.'), 'error');
        return;
        }
    
        // Check if the user has one of the specified roles.
        $user_roles = $user->getRoles();
        $has_target_role = array_intersect($user_roles, $roles_to_check);
    
        if (empty($has_target_role)) {
        return;
        }
    
        // Delete all content (nodes) created by the user.
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nids = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('uid', $uid)
        ->execute();
    
        if (!empty($nids)) {
        $nodes = $node_storage->loadMultiple($nids);
        $node_storage->delete($nodes);
        \Drupal::messenger()->addMessage(t('All content created by user @uid has been deleted.', ['@uid' => $uid]));
        }
    
        // Delete all files owned by the user.
        $file_storage = \Drupal::entityTypeManager()->getStorage('file');
        $fids = \Drupal::entityQuery('file')
            ->accessCheck(TRUE)
            ->condition('uid', $uid)
            ->execute();
    
        if (!empty($fids)) {
            $files = $file_storage->loadMultiple($fids);
            foreach ($files as $file) {
                $file->delete();
            }
        \Drupal::messenger()->addMessage(t('All files owned by user @uid have been deleted.', ['@uid' => $uid]));
        }
    
        // Delete the user account.
        $user->delete();
        \Drupal::messenger()->addMessage(t('User account @uid has been deleted.', ['@uid' => $uid]));
    }

    /**
     * Function to load all users.
     */
    private function loadAllUsers() {

        // Query to get all user IDs.
        $uids = \Drupal::entityQuery('user')
        ->accessCheck(TRUE)
        ->execute();

        if (!empty($uids)) {
            // Process users.
            foreach ($uids as $uid) {
                $this->delete_user_with_specific_roles($uid);            
            }
        }
        else {
            \Drupal::messenger()->addMessage(t('No users found.'));
        }
    }

    /**
     * Function to delete application
     * related nodes creates by admin
     */
    private function DeleteAdminNodes() {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        if (in_array('administrator', $roles)) {
           // Delete evaluations owned by the admin.
            $nids = \Drupal::entityQuery('node')
                ->accessCheck(TRUE)
                ->condition('type', 'evaluation_form')
                ->condition('uid', $user->id())
                ->execute();
            if (!empty($nids)) {
                $node_storage = \Drupal::entityTypeManager()->getStorage('node');     
                $nodes = $node_storage->loadMultiple($nids);
                $node_storage->delete($nodes);
             }
            // Delete all files owned by the admin.
            $file_storage = \Drupal::entityTypeManager()->getStorage('file');
            $fids = \Drupal::entityQuery('file')
                ->accessCheck(TRUE)
               // ->condition('uid', $user->id())
                ->execute();
            if (!empty($fids)) {
                $files = $file_storage->loadMultiple($fids);
                foreach ($files as $file) {
                    $file->delete();
                }
            }
        }       
    }

    /**
     * Delete directories and zip files inside the private directory.
     */
    private function deletePrivateDirectoriesAndZipFiles() {
        try {
            $file_system = \Drupal::service('file_system');
            
            // Get the private directory path
            $private_dir = 'private://';
            $real_private_dir = $file_system->realpath($private_dir);
            
            if (!$real_private_dir || !is_dir($real_private_dir)) {
                \Drupal::messenger()->addMessage($this->t('Private directory not found or not accessible.'));
                return;
            }
            
            // Delete all zip files in the private directory
            $zip_files = glob($real_private_dir . '/*.zip');
            $zip_count = 0;
            
            foreach ($zip_files as $zip_file) {
                if (is_file($zip_file) && unlink($zip_file)) {
                    $zip_count++;
                }
            }
            
            if ($zip_count > 0) {
                \Drupal::messenger()->addMessage($this->t('Deleted @count zip files from the private directory.', ['@count' => $zip_count]));
            }
            
            // Get all directories in the private folder
            $directories = glob($real_private_dir . '/*', GLOB_ONLYDIR);
            $dir_count = 0;
            
            // Delete each directory completely
            foreach ($directories as $dir) {
                // Count files before deletion for reporting
                $file_count = $this->countFilesInDirectory($dir);
                
                // Recursively delete the directory and its contents completely
                if ($this->recursiveDirectoryDelete($dir, true)) {
                    $dir_count++;
                    \Drupal::messenger()->addMessage($this->t('Deleted directory @dir and removed @count files/directories.', 
                        ['@dir' => basename($dir), '@count' => $file_count]));
                }
            }
            
            if ($dir_count > 0) {
                \Drupal::messenger()->addMessage($this->t('Successfully deleted @count directories from the private folder.', ['@count' => $dir_count]));
            } else {
                \Drupal::messenger()->addMessage($this->t('No directories found in the private folder to delete.'));
            }
            
        } catch (\Exception $e) {
            \Drupal::messenger()->addError($this->t('Error cleaning private directories: @message', ['@message' => $e->getMessage()]));
            \Drupal::logger('research_application_workflow')->error('Error cleaning private directories: @message', ['@message' => $e->getMessage()]);
        }
    }
    
    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir
     *   The directory path to delete.
     * @param bool $remove_dir
     *   Whether to remove the directory itself or just its contents.
     * 
     * @return bool
     *   TRUE if the directory was successfully deleted, FALSE otherwise.
     */
    private function recursiveDirectoryDelete($dir, $remove_dir = false) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($path)) {
                    $this->recursiveDirectoryDelete($path, true);
                } else {
                    unlink($path);
                }
            }
        }
        
        // Remove the directory itself if requested
        if ($remove_dir) {
            return rmdir($dir);
        }
        
        return true;
    }

    /**
     * Count the number of files and directories in a directory recursively.
     *
     * @param string $dir
     *   The directory path to count.
     *
     * @return int
     *   The number of files and directories.
     */
    private function countFilesInDirectory($dir) {
        $count = 0;
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    $count++;
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                        $count += $this->countFilesInDirectory($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
        }
        return $count;
    }
} 