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
With <strong>100.000</strong> includes the render time on a local machine is around <strong>1300ms</strong>, with a flattened
template render time is around <strong>40ms</strong>. These numbers show us that the overhead of an ``@include`` is around <strong>30</strong> times more expensive than the actual rendering of this simple component inside a for loop.

In real live examples with big tables performance gain is of course considerably smaller. An example: A hot endpoint from a project I'm working on was able to handle twice as many requests when views were flattened (a big view with around 400 nested @includes).

#### What's the catch?
When running ``php artisan view:flatten`` your templates won't necessarily update 
automatically on change, you'll have to run ``php artisan view:cache`` or ``php artisan view:flatten`` again. The command is only suitable for a production environment where you don't plan to change views.
Variables that are declared in a view are generally unset afterwards. This is detected via a regex and might not include all forms of variable declaration, leading to a potentially different behaviour if you tend to write a lot of plain PHP code in your templates.

#### What's next
It would be interesting to achieve something similar for components or test the behaviour with component libraries.
It might be possible to similarily render an anonymous ``@component`` inside an anonymous funtion.
