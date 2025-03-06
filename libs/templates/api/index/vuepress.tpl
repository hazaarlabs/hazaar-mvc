export default [
    {
        text: "Functions",
        collapsable: true,
        children: [
{foreach $functions as $function}            {
                text: "{$function->name}",
                link: "/api/function/{$function->name}"
            },
{/foreach}
        ]
    }
]