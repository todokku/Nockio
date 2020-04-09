<?php

include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "api.php";
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

class Nockio
{

    // Constants
    public const API = "nockio";

    // Defaults
    private const DEFAULT_USER = "Administrator";

    // Directory roots
    private const DIRECTORY_ROOT = DIRECTORY_SEPARATOR . "var" . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "nockio";

    // Subdirectory roots
    private const DIRECTORY_GIT = self::DIRECTORY_ROOT . DIRECTORY_SEPARATOR . "git";

    public static function initialize()
    {
    }

    public static function handle()
    {
        Base::handle(function ($action, $parameters) {
            // Requires authentication
            Authenticate::initialize();
            if ($action === "setUp") {
                if (Authenticate::signUp(self::DEFAULT_USER, null)[1] !== "User already exists") {
                    if (isset($parameters->password)) {
                        if (is_string($parameters->password)) {
                            Authenticate::signUp("Administrator", $parameters->password);
                            return [true, "Setup finished"];
                        }
                        return [false, "Invalid parameters"];
                    }
                    return [false, "Missing parameters"];
                }
                return [false, "Already set-up"];
            } else {
                // Check for token
                if (isset($parameters->token) && is_string($parameters->token)) {
                    $authentication = Authenticate::validate($parameters->token);
                    if ($authentication[0]) {
                        // Authenticated
                        if ($action === "listApplications") {
                            // List the directories in the host directory
                            $hostDirectory = Utility::evaluateDirectory("applications", self::API);
                            // Array of files
                            $paths = scandir($hostDirectory);
                            // Remove "." and ".."
                            $paths = array_slice($paths, 2);
                            // Return the array
                            return [true, $paths];
                        } else if ($action === "listDeployments") {
                            if (isset($parameters->application)) {
                                if (is_string($parameters->application)) {
                                    $applicationName = basename($parameters->application);
                                    // List the files in the application directory
                                    $applicationDirectory = Utility::evaluateFile("applications:$applicationName", self::API);
                                    // Array of files
                                    $paths = scandir($applicationDirectory);
                                    // Remove "." and ".."
                                    $paths = array_slice($paths, 2);
                                    // Return the array
                                    return [true, $paths];
                                }
                                return [false, "Invalid parameters"];
                            }
                            return [false, "Missing parameters"];
                        } else if ($action === "printDeployment") {
                            if (isset($parameters->application) && isset($parameters->deployment)) {
                                if (is_string($parameters->application) && is_string($parameters->deployment)) {
                                    $applicationName = basename($parameters->application);
                                    $deploymentName = basename($parameters->deployment);
                                    // Find the file
                                    $deploymentFile = Utility::evaluateFile("applications:$applicationName:$deploymentName", self::API);
                                    // Make sure the file exists
                                    if (file_exists($deploymentFile)) {
                                        // Return the contents
                                        return [true, json_decode(file_get_contents($deploymentFile))];
                                    }
                                    return [false, "Deployment does not exist"];
                                }
                                return [false, "Invalid parameters"];
                            }
                            return [false, "Missing parameters"];
                        } else if ($action === "createDeployment") {
                            if (isset($parameters->application) && isset($parameters->deployment)) {
                                if (is_string($parameters->application) && is_string($parameters->deployment)) {
                                    $applicationName = basename($parameters->application);
                                    $deploymentName = basename($parameters->deployment);
                                    // Find the file
                                    $deploymentFile = Utility::evaluateFile("applications:$applicationName:$deploymentName", self::API);
                                    // Make sure the file exists
                                    if (!file_exists($deploymentFile)) {
                                        // Create a deployment file
                                        file_put_contents($deploymentFile, json_encode(new stdClass()));
                                        // Create a new Git repository
                                        $targetGitDirectory = self::DIRECTORY_GIT . DIRECTORY_SEPARATOR . "sources" . DIRECTORY_SEPARATOR . $applicationName . DIRECTORY_SEPARATOR . $deploymentName;
                                        // Create the target directory
                                        mkdir($targetGitDirectory, 0777, true);
                                        // Create the repository
                                        shell_exec("git init --bare $targetGitDirectory");
                                        // Change the permissions
                                        shell_exec("chmod 777 -R $targetGitDirectory");
                                        // Return the contents
                                        return [true, null];
                                    }
                                    return [false, "Deployment already exists"];
                                }
                                return [false, "Invalid parameters"];
                            }
                            return [false, "Missing parameters"];
                        } else if ($action === "modifyDeployment") {

                        } else if ($action === "addPublicKey") {
                            if (isset($parameters->key) && is_string($parameters->key)) {
                                // Find authorized_keys path
                                $targetFilePath = self::DIRECTORY_GIT . DIRECTORY_SEPARATOR . "ssh" . DIRECTORY_SEPARATOR . "authorized_keys";
                                // Read contents
                                $contents = "";
                                if (file_exists($targetFilePath)) {
                                    $contents = file_get_contents($targetFilePath);
                                }
                                // Append key
                                $contents .= $parameters->key;
                                $contents .= "\n";
                                // Write file
                                file_put_contents($targetFilePath, $contents);
                                return [true, null];
                            }
                            return [false, "Missing parameters"];
                        }
                        return [false, "Unknown hook"];
                    }
                }
                return [false, "Authentication failure"];
            }
        });
    }

}