document.addEventListener("click", function (e) {
  const btn = e.target.closest(".uifrommars-like-btn");
  if (!btn) return;

  const container = btn.closest(".uifrommars-like-button");
  const postId = container.dataset.postId;
  const countEl = container.querySelector(".uifrommars-like-count");

  btn.disabled = true;

  fetch(uifrommarsLike.ajax_url, {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
    body: new URLSearchParams({
      action: "uifrommars_like_post",
      post_id: postId,
      nonce: uifrommarsLike.nonce,
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        countEl.textContent = data.data.count;
        btn.classList.add("liked");
      }
    })
    .finally(() => {
      btn.disabled = false;
    });
});

// top-liked-block.js (save to /js/top-liked-block.js)
(function (blocks, element, components, editor) {
  var el = element.createElement;
  var registerBlockType = blocks.registerBlockType;
  var InspectorControls = editor.InspectorControls;
  var TextControl = components.TextControl;
  var RangeControl = components.RangeControl;
  var PanelBody = components.PanelBody;

  registerBlockType("uifrommars/top-liked-posts", {
    title: "Top Liked Posts",
    icon: "heart",
    category: "widgets",

    attributes: {
      title: {
        type: "string",
        default: "Top Liked Posts",
      },
      count: {
        type: "number",
        default: 5,
      },
    },

    edit: function (props) {
      var attributes = props.attributes;

      return [
        el(
          InspectorControls,
          { key: "inspector" },
          el(
            PanelBody,
            { title: "Settings", initialOpen: true },
            el(TextControl, {
              label: "Title",
              value: attributes.title,
              onChange: function (value) {
                props.setAttributes({ title: value });
              },
            }),
            el(RangeControl, {
              label: "Number of posts to show",
              value: attributes.count,
              min: 1,
              max: 20,
              onChange: function (value) {
                props.setAttributes({ count: value });
              },
            })
          )
        ),
        el(
          "div",
          { className: props.className },
          el(
            "div",
            { className: "uifrommars-block-preview" },
            el("h3", {}, attributes.title),
            el("p", {}, "This block will display your " + attributes.count + " most liked posts.")
          )
        ),
      ];
    },

    save: function () {
      // Dynamic block, render is handled by PHP
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor);
