@php
    $categories = \DB::table('tb_icon_categories')->orderBy('name')->get();
    $icons = \DB::table('tb_icons')->orderBy('name')->get();
@endphp
<div class="p-3">
    <div class="row mb-3">
        <div class="col-md-12">
            <input type="text" id="iconSearch" class="form-control" placeholder="Search icons...">
        </div>
    </div>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="iconTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="all-tab" data-bs-toggle="tab" href="#all-icons" role="tab">All Icons</a>
        </li>
        @foreach($categories as $category)
            <li class="nav-item">
                <a class="nav-link" id="cat-{{ $category->id }}-tab" data-bs-toggle="tab" href="#cat-{{ $category->id }}" role="tab">{{ $category->name }}</a>
            </li>
        @endforeach
    </ul>

    <!-- Tab panes -->
    <div class="tab-content pt-3" id="iconTabContent" style="max-height: 400px; overflow-y: auto;">
        
        <!-- All Icons Tab -->
        <div class="tab-pane active" id="all-icons" role="tabpanel">
            <div class="row" id="allIconsContainer">
                @foreach($icons as $icon)
                    @php
                        $cssClass = $icon->icon_class;
                        $catName = '';
                        $cat = collect($categories)->firstWhere('id', $icon->category_id);
                        if($cat) {
                            $catName = strtolower($cat->name);
                        }
                        
                        if (strpos($catName, 'fontawesome') !== false && !Str::contains($cssClass, ['fa ', 'fas ', 'far ', 'fab '])) {
                            $cssClass = 'fas ' . $cssClass;
                        } elseif (strpos($catName, 'flag') !== false && !Str::contains($cssClass, 'flag-icon')) {
                             $cssClass = 'flag-icon ' . $cssClass;
                        }
                    @endphp
                    <div class="col-md-2 col-3 mb-3 text-center icon-item" data-name="{{ strtolower($icon->name) }}" data-class="{{ $icon->icon_class }}">
                        <a href="javascript:void(0)" onclick="selectIcon('{{ $cssClass }}')" class="d-block p-2 border rounded hover-shadow text-decoration-none text-dark">
                            <div class="mb-2" style="font-size: 24px;">
                                @if($icon->type == 'code' && SiteHelpers::isSafeSvgString($icon->svg_code))
                                    {!! $icon->svg_code !!}
                                @elseif($icon->type == 'file')
                                    <img src="{{ asset($icon->file_path) }}" alt="{{ $icon->name }}" style="max-width: 100%; height: 24px;">
                                @elseif($icon->type == 'url')
                                    <img src="{{ $icon->icon_url }}" alt="{{ $icon->name }}" style="max-width: 100%; height: 24px;">
                                @elseif($icon->type == 'class')
                                    <i class="{{ $cssClass }}"></i>
                                @endif
                            </div>
                            <div class="small text-truncate" title="{{ $icon->name }}">{{ $icon->name }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Category Tabs -->
        @foreach($categories as $category)
            <div class="tab-pane fade" id="cat-{{ $category->id }}" role="tabpanel">
                <div class="row">
                    @foreach($icons->where('category_id', $category->id) as $icon)
                        @php
                            $cssClass = $icon->icon_class;
                            $catName = strtolower($category->name);
                            if (strpos($catName, 'fontawesome') !== false && !Str::contains($cssClass, ['fa ', 'fas ', 'far ', 'fab '])) {
                                if (strpos($catName, 'brand') !== false) {
                                    $cssClass = 'fab ' . $cssClass;
                                } else {
                                    $cssClass = 'fas ' . $cssClass;
                                }
                            } elseif (strpos($catName, 'flag') !== false && !Str::contains($cssClass, 'flag-icon')) {
                                 $cssClass = 'flag-icon ' . $cssClass;
                            }
                        @endphp
                        <div class="col-md-2 col-3 mb-3 text-center icon-item" data-name="{{ strtolower($icon->name) }}">
                            <a href="javascript:void(0)" onclick="selectIcon('{{ $cssClass }}')" class="d-block p-2 border rounded hover-shadow text-decoration-none text-dark">
                                <div class="mb-2" style="font-size: 24px;">
                                    @if($icon->type == 'code' && SiteHelpers::isSafeSvgString($icon->svg_code))
                                        {!! $icon->svg_code !!}
                                    @elseif($icon->type == 'file')
                                        <img src="{{ asset($icon->file_path) }}" alt="{{ $icon->name }}" style="max-width: 100%; height: 24px;">
                                    @elseif($icon->type == 'url')
                                        <img src="{{ $icon->icon_url }}" alt="{{ $icon->name }}" style="max-width: 100%; height: 24px;">
                                    @elseif($icon->type == 'class')
                                        <i class="{{ $cssClass }}"></i>
                                    @endif
                                </div>
                                <div class="small text-truncate" title="{{ $icon->name }}">{{ $icon->name }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

    </div>
</div>

<style>
.hover-shadow:hover {
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    background-color: #f8f9fa;
}
.icon-item a { border-color: #eee; }
</style>

<script>
function selectIcon(iconClass) {
    // Populate the input in the parent window (Sximo menu builder logic)
    $('input[name=menu_icons]').val(iconClass);
    $('#sximo-modal').modal('hide');
}

$(document).ready(function() {
    // Search functionality
    $('#iconSearch').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('.icon-item').filter(function() {
            $(this).toggle($(this).data('name').indexOf(value) > -1)
        });
    });
});
</script>