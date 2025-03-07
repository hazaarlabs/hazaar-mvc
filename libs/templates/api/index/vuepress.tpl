export default [
    {
        text: "{$project.title}",
        link: "/api/home.md"
    },
    {include file="namespace.tpl" namespaces=$namespaces functions=$functions interfaces=$interfaces traits=$traits classes=$classes}
]