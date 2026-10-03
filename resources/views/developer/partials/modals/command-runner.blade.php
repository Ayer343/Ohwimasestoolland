<div id="commandRunnerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Command Runner</h3>
                <button onclick="closeCommandRunner()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Command</label>
                <select id="commandSelect" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">-- Select a command --</option>
                    <option value="migrate">Database Migrations</option>
                    <option value="db:seed">Seed Database</option>
                    <option value="cache:clear">Clear Cache</option>
                    <option value="view:clear">Clear Views</option>
                    <option value="route:clear">Clear Routes</option>
                    <option value="config:clear">Clear Config</option>
                    <option value="optimize:clear">Clear All</option>
                    <option value="queue:restart">Restart Queue</option>
                    <option value="storage:link">Link Storage</option>
                    <option value="backup:run">Run Backup</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Custom Command</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        php artisan
                    </span>
                    <input type="text" id="customCommand" 
                        class="flex-1 rounded-none rounded-r-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        placeholder="Enter custom Artisan command...">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Command Output</label>
                <div id="commandOutput" class="w-full h-48 bg-gray-900 text-green-400 font-mono text-sm p-3 rounded-lg overflow-auto">
                    <div class="text-gray-400">Command output will appear here...</div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t dark:border-gray-700">
                <button onclick="closeCommandRunner()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:text-gray-300">
                    Close
                </button>
                <button onclick="runSelectedCommand()" 
                    class="px-4 py-2 text-sm font-medium text-white bg-green-500 hover:bg-green-600 rounded-lg">
                    <i class="fas fa-play mr-1"></i> Run Command
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showCommandRunner() {
    document.getElementById('commandRunnerModal').classList.remove('hidden');
}

function closeCommandRunner() {
    document.getElementById('commandRunnerModal').classList.add('hidden');
}

function runSelectedCommand() {
    const commandSelect = document.getElementById('commandSelect');
    const customCommand = document.getElementById('customCommand').value;
    
    let command = '';
    if (customCommand) {
        command = customCommand;
    } else if (commandSelect.value) {
        command = commandSelect.value;
    } else {
        showNotification('Please select or enter a command', 'warning');
        return;
    }
    
    const outputDiv = document.getElementById('commandOutput');
    outputDiv.innerHTML = `
        <div class="flex items-center">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-green-500 mr-2"></div>
            <span>Running command: <span class="text-yellow-300">php artisan ${command}</span></span>
        </div>
    `;
    
    fetch('{{ route("developer.commands.run") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ command: command })
    })
    .then(response => response.json())
    .then(data => {
        let html = `
            <div class="text-green-400">
                <div class="flex items-center mb-2">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Command executed successfully</span>
                </div>
                <div class="text-gray-300 mb-2">Output:</div>
                <div class="whitespace-pre-wrap">${data.output || 'No output'}</div>
            </div>
        `;
        
        if (data.warnings && data.warnings.length > 0) {
            html += `<div class="text-yellow-400 mt-2">Warnings: ${data.warnings.join(', ')}</div>`;
        }
        
        outputDiv.innerHTML = html;
    })
    .catch(error => {
        outputDiv.innerHTML = `
            <div class="text-red-400">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span>Command failed</span>
                </div>
                <div class="text-gray-300">Error:</div>
                <div class="whitespace-pre-wrap">${error.message}</div>
            </div>
        `;
    });
}

// Update custom command when selection changes
document.getElementById('commandSelect').addEventListener('change', function() {
    if (this.value) {
        document.getElementById('customCommand').value = this.value;
    }
});
</script>