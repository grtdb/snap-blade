<?php

namespace Snap\Blade;

use Snap\Core\Snap;
use Snap\Services\Provider;
use Snap\Templating\Templating_Interface;
use eftec\bladeone\BladeOne;

/**
 * Snap Debug service provider.
 */
class Blade_Provider extends Provider
{
    /**
     * Register the Service.
     *
     * @since  1.0.0
     */
    public function register()
    {
        $this->add_config_location(\realpath(__DIR__ . '/../config'));

        $this->add_blade();

        Snap::services()->add(
            Strategy::class,
            function ($hodl) {
                return new Strategy;
            }
        );

        // Bind blade strategy as the current Templating engine.
        Snap::services()->bind(Strategy::class, Templating_Interface::class);
    }

    /**
     * Creates the Snap_blade instance, and adds to service container.
     *
     * @since  1.0.0
     */
    public function add_blade()
    {
        $blade = new Snap_Blade(
            \locate_template(Snap::config('theme.templates_directory')),
            \locate_template(\trailingslashit(Snap::config('theme.cache_directory')) . 'templates'),
            BladeOne::MODE_SLOW
        );

        // Set the @inject directive to resolve from the service container.
        $blade->setInjectResolver(
            function ($namespace, $variable_name) {
                return Snap::services()->get($namespace);
            }
        );

        // Set the current user.
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $blade->setAuth($current_user->data->display_name);
        }

        // Set the @can directive.
        $blade->setCanFunction(
            function ($action = null, $subject = null) {
                if ($subject === null) {
                    return current_user_can($action);
                }

                return user_can($subject, $action);
            }
        );

        // Set the @canany directive.
        $blade->setAnyFunction(
            function ($array = []) {
                foreach ($array as $permission) {
                    if (current_user_can($permission)) {
                        return true;
                    }
                }
            
                return false;
            }
        );

        $this->add_directives($blade);

        Snap::services()->addSingleton(
            Snap_Blade::class,
            function () use ($blade) {
                return $blade;
            }
        );

        Snap::services()->alias(Snap_Blade::class, 'blade');
    }

    /**
     * Add custom directives to blade.
     *
     * @since  1.0.0
     *
     * @param BladeOne $blade The BladeOne service.
     */
    private function add_directives($blade)
    {
        $blade->directive(
            'wphead',
            function () {
                return '<?php wp_head(); ?>';
            }
        );

        $blade->directive(
            'wpfooter',
            function () {
                return '<?php wp_footer(); ?>';
            }
        );

        $blade->directive(
            'sidebar',
            function ($input) {
                return "<?php dynamic_sidebar{$input}; ?>";
            }
        );

        $blade->directive(
            'thecontent',
            function ($input) {
                return "<?php the_content(); ?>";
            }
        );

        $blade->directive(
            'theexcerpt',
            function ($input) {
                return "<?php the_excerpt(); ?>";
            }
        );

        $blade->directive(
            'navmenu',
            function ($input) {
                return "<?php wp_nav_menu{$input}; ?>";
            }
        );

        $blade->directive(
            'simplemenu',
            function ($expression) {
                \preg_match('/\( *(.*) * as *([^\)]*)/', $expression, $matches);
                
                $iteratee = \trim($matches[1]);
                
                $iteration = \trim($matches[2]);
                
                $init_loop = "\$__currentLoopData = Snap\Core\Utils::get_nav_menu($iteratee); 
                    \$this->addLoop(\$__currentLoopData);";
               
                $iterate_loop = '$this->incrementLoopIndices(); 
                    $loop = $this->getFirstLoop();';

                return "<?php {$init_loop} foreach(\$__currentLoopData as {$iteration}): {$iterate_loop} ?>";
            }
        );

        $blade->directive(
            'endsimplemenu',
            function ($expression) {
                return '<?php endforeach; $this->popLoop(); $loop = $this->getFirstLoop(); ?>';
            }
        );

        $blade->directive(
            'partial',
            function ($input) {
                $input = 'partials.' . \trim($input, '()\'');
                return '<?php echo $this->runChild(\''.$input.'\'); ?>';
            }
        );

        $blade->directive(
            'paginate',
            function ($input = []) {
                $input = $input ?? '[]';

                return '
			<?php
			$pagination = \Snap\Core\Snap::services()->resolve(
	            \Snap\Modules\Pagination::class,
	            [
	                \'args\' => ' . ($input) .',
	            ]
	        );

	        echo $pagination->get();
	        ?>';
            }
        );

        $blade->directive(
            'loop',
            function () {
                return '<?php if ($wp_query->have_posts()) : 
                    while ($wp_query->have_posts()) : $wp_query->the_post(); ?>';
            }
        );

        $blade->directive(
            'endloop',
            function () {
                return '<?php endwhile; endif; ?>';
            }
        );
    }
}
