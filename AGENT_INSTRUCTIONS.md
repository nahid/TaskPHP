# AGENT_INSTRUCTIONS.md

Welcome, AI Agent! This document reflects the **refactored minimal API** of TaskPHP.

## Project Overview
**TaskPHP** (`Nahid\TaskPHP`) is a high-performance concurrency library for PHP 7.4+. It uses child processes (`proc_open`) and IPC over pipes.

## The Minimal API
The library has been refactored to prioritize the `async/await` pattern. Legacy methods like `defer`, `concurrent`, and `background` have been removed.

### Core Chain
1.  **Configure**: `Task::limit(int)`, `Task::timeout(int)`, `Task::bootstrap(mixed)`, `Task::failFast(bool)`, `Task::outputLimit(int)`.
2.  **Execute**: `->async(array $tasks)` -> returns a `TaskGroup`.
3.  **Resolve**:
    - `->await(?callable $callback)`: Blocks until done. Optional callback to process results.
    - `->forget()`: Detaches the process to run in the background.

### Example
```php
$results = Task::limit(5)
    ->async(['key' => fn() => 'data'])
    ->await();
```

---

## Technical Context
- **Worker**: `src/bin/worker.php` handles execution.
- **Bootstrapping**: `bootstrap()` method replaces both path-based and object-based setters. It accepts a string path or a `TaskBootstrapInterface` object.
- **Process Management**: `ProcessManager` handles the lifecycle. It supports concurrency limits and timeouts.

## Development Rules
1.  **Internal Methods**: Use `dispatch` logic inside `Task::async()`.
2.  **No Side Effects**: Do not use `echo` in workers; it corrupts data.
3.  **PHP Compatibility**: Maintain support for PHP 7.4. Use docblocks instead of union types in class properties if possible for broad compatibility.
