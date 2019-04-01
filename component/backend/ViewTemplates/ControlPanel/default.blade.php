<?php
/**
 *  @package AkeebaSubs
 *  @copyright Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;
?>

{{-- Include external sections. Do note how you can include sub-templates in one order and compile them in a completely
different order using @yield later on! --}}
@include('admin:com_akeebasubs/ControlPanel/phpversion')
@include('admin:com_akeebasubs/ControlPanel/graphs')
@include('admin:com_akeebasubs/ControlPanel/stats')
{{-- Note: I don't pass $this->hasGeoIPPlugin and $this->geoIPPluginNeedsUpdate. This demonstrates how Blade
subtemplates can view their parent's variables automatically. --}}
@include('admin:com_akeebasubs/ControlPanel/geoip')

{{-- Compile the output. Do note that I don't need to wrap it in a section. Content outside a section is yielded
immediately. Alternatively I could wrap this in a @section/@show block or even @section/@stop and use @yield to
render it. --}}
@yield('phpVersionWarning', '')

<div id="updateNotice"></div>

@yield('geoip', '')

<div class="akeeba-container--50-50">
    <div>
        @yield('graphs', '')
    </div>
    <div>
        @yield('stats', '')

        @modules('akeebasubscriptionsstats')

        @include('admin:com_akeebasubs/ControlPanel/quickicons')
        @yield('quickicons', '')
    </div>
</div>

<div class="akeeba-container--100">
    <div>
        @include('admin:com_akeebasubs/ControlPanel/footer')
        @yield('footer')
    </div>
</div>

<script type="text/javascript">
    (function($) {
        $(document).ready(function(){
            $.ajax('index.php?option=com_akeebasubs&view=ControlPanel&task=updateinfo&tmpl=component', {
                success: function(msg, textStatus, jqXHR)
                {
                    // Get rid of junk before and after data
                    var match = msg.match(/###([\s\S]*?)###/);
                    data = match[1];

                    if (data.length)
                    {
                        $('#updateNotice').html(data);
                    }
                }
            })
        });
    })(akeeba.jQuery);
</script>
