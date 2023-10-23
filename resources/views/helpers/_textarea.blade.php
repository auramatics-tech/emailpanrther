<textarea 
    {{ isset($readonly) ? "readonly='readonly'" : "" }}
    type="text"
    name="{{ $name }}"
    class="form-control{{ $classes }} {{ isset($class) ? $class : "" }}"
    {{ isset($disabled) && $disabled == true ? ' disabled="disabled"' : "" }}
    rows="{{ isset($rows) && $rows ? $rows : '' }}"
>{{ isset($value) ? $value : "" }}</textarea>
