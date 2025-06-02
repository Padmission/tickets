export default function render(templateContent) {
  const template = document.createElement('template');
  template.innerHTML = templateContent;

  return template.content.cloneNode(true);
}
