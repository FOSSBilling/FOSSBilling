<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="/admin/settings">
                        @csrf

                    @foreach ($settings as $setting)
                    <div class="col-span-4">
                        <label for="sku" class="block text-sm font-medium text-gray-700">
                            {{$setting->key}}:
                        </label>
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <input type="text" name="settings[{{$setting->key}}]" id="settings[{{$setting->key}}]"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full
                                                   shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                   value="{{$setting->value}}"
                                   />
                        </div>
                    </div>    
                    @endforeach
                    <button type="submit"
                    class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md border border-transparent shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Save
                </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
