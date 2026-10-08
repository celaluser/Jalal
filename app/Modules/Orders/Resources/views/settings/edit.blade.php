<x-layouts.app :title="__('orders.settings_title')">
    <x-ui.page-header :title="__('orders.settings_title')" :description="__('orders.settings_sub')" />
    <form method="POST" action="{{ route('orders.settings.update') }}" class="max-w-3xl space-y-5">
        @csrf @method('PUT')

        <x-ui.card :title="__('orders.sec_open')">
            <div class="space-y-4">
                <x-ui.checkbox name="enabled" :label="__('orders.enabled')" :checked="$s['enabled']" />
                <x-ui.input name="paused_message" :label="__('orders.paused_message')" :value="$s['paused_message']" maxlength="200" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_types')">
            <div class="space-y-3">
                <x-ui.checkbox name="dine_in" :label="__('orders.dine_in')" :checked="$s['dine_in']" />
                <x-ui.checkbox name="dine_in_pick_table" :label="__('orders.dine_in_pick_table')" :checked="$s['dine_in_pick_table']" />
                <x-ui.checkbox name="require_name" :label="__('orders.require_name')" :checked="$s['require_name']" />
                <x-ui.checkbox name="takeaway" :label="__('orders.takeaway')" :checked="$s['takeaway']" />
                <x-ui.checkbox name="delivery" :label="__('orders.delivery')" :checked="$s['delivery']" />
                <x-ui.checkbox name="curbside" :label="__('orders.curbside')" :checked="$s['curbside']" />
                <x-ui.checkbox name="room_service" :label="__('orders.room_service')" :checked="$s['room_service']" />
                @error('dine_in')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                <div class="grid gap-4 pt-2 sm:grid-cols-2">
                    <x-ui.input name="delivery_fee" type="number" step="0.01" min="0" inputmode="decimal" :label="__('orders.delivery_fee')" :value="$s['delivery_fee']" />
                    <x-ui.input name="delivery_min" type="number" step="0.01" min="0" inputmode="decimal" :label="__('orders.delivery_min')" :value="$s['delivery_min']" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_pricing')">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="tax_rate" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('orders.tax_rate')" :value="$s['tax_rate']" />
                    <x-ui.input name="service_rate" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('orders.service_rate')" :value="$s['service_rate']" />
                </div>
                <x-ui.checkbox name="prices_include_tax" :label="__('orders.prices_include_tax')" :checked="$s['prices_include_tax']" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_packaging')" :description="__('orders.packaging_help')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="packaging_fee" type="number" step="0.01" min="0" inputmode="decimal" :label="__('orders.packaging_fee')" :value="$s['packaging_fee']" />
                <x-ui.input name="packaging_per_item" type="number" step="0.01" min="0" inputmode="decimal" :label="__('orders.packaging_per_item')" :value="$s['packaging_per_item']" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_payment')" :description="__('orders.payment_help')">
            <div class="space-y-3">
                <x-ui.checkbox name="pay_cash" :label="__('orders.setting_pay_cash')" :checked="$s['pay_cash']" />
                <x-ui.checkbox name="pay_card" :label="__('orders.setting_pay_card')" :checked="$s['pay_card']" />
                @error('pay_cash')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_flow')">
            <div class="space-y-4">
                <x-ui.checkbox name="auto_accept" :label="__('orders.auto_accept')" :checked="$s['auto_accept']" />
                <x-ui.checkbox name="allow_cancel" :label="__('orders.allow_cancel')" :checked="$s['allow_cancel']" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="prep_minutes" type="number" min="1" max="240" :label="__('orders.prep_minutes')" :value="$s['prep_minutes']" />
                    <x-ui.input name="wait_per_order" type="number" min="0" max="30" :label="__('orders.wait_per_order')" :value="$s['wait_per_order']" :hint="__('orders.wait_per_order_hint')" />
                    <x-ui.input name="max_items" type="number" min="0" max="500" :label="__('orders.max_items')" :value="$s['max_items']" :hint="__('orders.max_items_hint')" />
                    <x-ui.input name="stations" :label="__('orders.stations')" :value="$s['stations']" maxlength="200" :hint="__('orders.stations_hint')" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_schedule')" :description="__('orders.schedule_help')">
            <div class="space-y-4">
                <x-ui.checkbox name="schedule_orders" :label="__('orders.schedule_orders')" :checked="$s['schedule_orders']" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="schedule_lead" type="number" min="0" max="1440" :label="__('orders.schedule_lead')" :value="$s['schedule_lead']" />
                    <x-ui.input name="schedule_days" type="number" min="1" max="30" :label="__('orders.schedule_days')" :value="$s['schedule_days']" />
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('orders.sec_notify')" :description="__('orders.notify_help')">
            <div class="space-y-3">
                <x-ui.checkbox name="notify_push" :label="__('orders.notify_push_setting')" :checked="$s['notify_push']" />
                <x-ui.checkbox name="notify_sms" :label="__('orders.notify_sms_setting')" :checked="$s['notify_sms']" />
                <x-ui.checkbox name="notify_whatsapp" :label="__('orders.notify_whatsapp_setting')" :checked="$s['notify_whatsapp']" />
            </div>
        </x-ui.card>

        <div class="flex justify-end"><x-ui.button :block="false" size="lg">{{ __('admin.save') }}</x-ui.button></div>
    </form>
</x-layouts.app>
