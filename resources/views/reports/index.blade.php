<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('KRA Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3>Generate Report</h3>

                    @if ($errors->any())
                        <div class="mb-4 font-medium text-sm text-red-600">
                            {{ __('Whoops! Something went wrong.') }}
                            <ul class="mt-3 list-disc list-inside text-sm text-red-600">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('reports.x-daily') }}" class="mt-4">
                        @csrf
                        <div class="mb-4">
                            <label for="taxpayer_pin_id" class="block text-sm font-medium text-gray-700">Taxpayer PIN</label>
                            <select name="taxpayer_pin_id" id="taxpayer_pin_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach ($taxpayerPins as $tpPin)
                                    <option value="{{ $tpPin->id }}">{{ $tpPin->pin }} - {{ $tpPin->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="kra_device_id" class="block text-sm font-medium text-gray-700">KRA Device</label>
                            <select name="kra_device_id" id="kra_device_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach ($taxpayerPins as $tpPin)
                                    @foreach ($tpPin->kraDevices as $device)
                                        <option value="{{ $device->id }}">{{ $device->kra_scu_id }} ({{ $device->device_type }} - {{ $device->status }})</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" formaction="{{ route('reports.x-daily') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Get X Daily Report
                            </button>
                            <button type="submit" formaction="{{ route('reports.z-daily') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Generate Z Daily Report
                            </button>
                            {{-- Add PLU report button here --}}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>