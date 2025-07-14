<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Z Daily Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-bold mb-4">Report Details (Z Daily)</h3>
                    <pre class="bg-gray-100 p-4 rounded overflow-auto text-sm">{{ json_encode($report, JSON_PRETTY_PRINT) }}</pre>

                    <h4 class="text-md font-bold mt-6 mb-2">Raw KRA Response</h4>
                    <pre class="bg-gray-100 p-4 rounded overflow-auto text-sm">{{ htmlspecialchars($kraResponse) }}</pre>

                    <div class="mt-6">
                        <a href="{{ route('reports.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-600 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            ← Back to Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>