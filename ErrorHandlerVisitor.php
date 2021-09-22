<?php

require 'vendor/autoload.php';

use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ErrorHandlerVisitor extends NodeVisitorAbstract
{
    public $error_handler_func_count = 0;
    public $error_handler_method_count = 0;
    public $error_handler_class_count = 0;

    private $error_handler_classes = [
        // PHP builtin classes
        'Exception', 'Error', 'Throwable', 'ErrorException', 'ArgumentCountError', 'ArithmeticError', 'AssertionError',
        'DivisionByZeroError', 'CompileError', 'ParseError', 'TypeError', 'ValueError', 'UnhandledMatchError',
        // phpMyAdmin: None
        // Drupal: None
        // Joomla
        'JError',
        // WordPress
        'WP_Error'
    ];

    private $error_handler_functions = [
        // PHP builtin functions
        'set_error_handler', 'set_exception_handler', 'error_clear_last', 'error_get_last', 'error_log', 'error_reporting',
        'restore_error_handler', 'restore_exception_handler', 'trigger_error', 'user_error',
        // phpMyAdmin: None
        // Drupal
        'watchdog_exception', '_drupal_log_error', '_drupal_exception_handler', '_drupal_decode_exception', '_drupal_error_handler_real',
        'error_displayable', '_drupal_render_exception_safe',
        // Joomla: None
        // WordPress: None
    ];

    private $error_handler_methods = [
        // PHP builtin methods: None
        // phpMyAdmin
        // 'Message' =>      ['rawError', 'error', 'rawNotice','notice'],
        'rawError', 'error', 'rawNotice', 'notice',
        // Drupal: None
        // Joomla
        // 'JError' =>       ['addToStack', 'attachHandler', 'customErrorHandler', 'customErrorPage', 'detachHandler', 'getError', 'getErrorHandling',
        //     'getErrors', 'handleCallback', 'handleDie', 'handleLog', 'isError', 'raise', 'raiseError','raiseNotice', 'raiseWarning',
        //     'registerErrorLevel', 'renderBacktrace', 'setErrorHandling', 'throwError', 'translateErrorLevel'],
        // 'JApplication' => ['enqueueMessage'],
        'addToStack', 'attachHandler', 'customErrorHandler', 'customErrorPage', 'detachHandler', 'getError', 'getErrorHandling',
        'getErrors', 'handleCallback', 'handleDie', 'handleLog', 'isError', 'raise', 'raiseError','raiseNotice', 'raiseWarning',
        'registerErrorLevel', 'renderBacktrace', 'setErrorHandling', 'throwError', 'translateErrorLevel',
        // WordPress
        // 'WP_Error' =>     ['get_error_code', 'get_error_codes', 'get_error_messages', 'get_error_message', 'get_error_data', 'add', 'add_data',
        //     'has_errors']
        'get_error_code', 'get_error_codes', 'get_error_messages', 'get_error_message', 'get_error_data', 'add_data',
        'has_errors'
    ];

    private $class_hierarchy = [];

    public function calculateClassCount() {
        $error_handling_extension_classes = [];
        foreach ($this->class_hierarchy as $parent_class => $child_classes) {
            if (in_array($parent_class, $this->error_handler_classes)) {
                $error_handling_extension_classes = array_merge($error_handling_extension_classes, $child_classes);
                $this->error_handler_class_count++;
            }
        }
        $this->error_handler_class_count += count($error_handling_extension_classes);
    }

    private static function getName(Node $node) {
        $node_name = null;
        if ($node instanceof Node\Name) {
            $node_name = implode('', $node->parts);
        }
        else if (isset($node->name)) {
            if (is_string($node->name)) {
                $node_name = $node->name;
            }
            else if ($node->name instanceof Node\Name) {
                $node_name = implode('', $node->name->parts);
            }
            else if ($node->name instanceof Node\Identifier) {
                $node_name = $node->name->name;
            }
        }
        return $node_name;
    }

    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\Class_) {
            $node_name = ErrorHandlerVisitor::getName($node);
            if (isset($node->extends)) {
                $extends = ErrorHandlerVisitor::getName($node->extends);
                $this->class_hierarchy[$extends][] = $node_name;
            }
            if (!array_key_exists($node_name, $this->class_hierarchy)) {
                $this->class_hierarchy[$node_name] = [];
            }
        }
        else if ($node instanceof Node\Expr\FuncCall) {
            // Handle func calls
            // Extract function name
            $node_name = ErrorHandlerVisitor::getName($node);
            if (in_array($node_name, $this->error_handler_functions)) {
                if ($node_name === 'error_log') {
                    if (isset($node->args[0]->value->left->left->value) &&
                              $node->args[0]->value->left->left->value === 'Removed file called (') {
                        // Skip error_log calls produced by Less is More
                        $this->error_handler_func_count--;
                    }
                    else if (isset($node->args[0]->value->value) &&
                        strpos($node->args[0]->value->value, 'Removed function called ') !== false) {
                        // Skip error_log calls produced by Less is More
                        $this->error_handler_func_count--;
                    }
                    else {
                        $what = 1;
                    }

                }
                $this->error_handler_func_count++;
            }
        }
        else if ($node instanceof Node\Expr\MethodCall) {
            // Handle method calls
            $node_name = ErrorHandlerVisitor::getName($node);
            if (in_array($node_name, $this->error_handler_methods)) {
                // Checking class names requires variable tracking
                // For now just comparing the method names
                $this->error_handler_method_count++;
            }
        }
        else if ($node instanceof Node\Expr\StaticCall) {
            $node_name = ErrorHandlerVisitor::getName($node);
            if (in_array($node_name, $this->error_handler_methods)) {
                // Checking class names requires variable tracking
                // For now just comparing the method names
                $this->error_handler_method_count++;
            }
        }
    }
}