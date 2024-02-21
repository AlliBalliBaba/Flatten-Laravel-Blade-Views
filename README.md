## Flattening Laravel Blade Files

Blade is a PHP templating engine built into Laravel.

Generally Blade is fast enough for almost all cases. This repo serves as
an idea of how someone could improve Blade performance, especially
when rendering large templates that utilize a lot of ``@include``.

Using ``@include`` (or components) is a lot slower than writing
everything into a single Blade file.

Most of the time we don't care about this slowness, splitting
templates into multiple files makes them more readable and reusable.
And most of the time Blade itself is not our main performance issue.

### How to speed up Blade

This repo includes a ``FlattenBladeFiles`` command, that can be used
to flatten all cached blade files into a single file.

This is achieved by temporarily replacing the ``@include`` directive
and instead loading the cached view files directly into the template.
This flattening is repeated some amount of times to accommodate nested
``@inludes``.

You can try it by copying the command into your project and running:

```
php artisan view:flatten
```

#### How much faster is it really?
We are gaining the most in terms of fastness when including a simple component a lot of times
As an example we are including this simple component <strong>100.000</strong> times in a ``@for`` loop:
```
@if(!empty($countryCode))
    <img src="https://somewebsite/{{$countryCode}}.png">
@endif
```
With <strong>100.000</strong> includes the render time is around <strong>1300ms</strong>, with a flattened
template render time is around <strong>50ms</strong>

In real live examples with big tables performance gain is of course considerably smaller, 
but still significant for large tables with 1000+ ``@includes``.

#### What's the catch?
When running ``php artisan view:flatten`` your templates won't necessarily update 
automatically on change, you'll have to run ``php artisan view:cache`` again.
Also in this current iteration, variables that are passed to the view 
<strong>are not unset afterwards</strong>, leading to a potentially different behaviour.