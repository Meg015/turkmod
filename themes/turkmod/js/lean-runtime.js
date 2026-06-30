(function () {
  "use strict";

  function collapseSiblingCategoryItems(currentItem) {
    var currentList = currentItem ? currentItem.parentElement : null;
    if (!currentList) {
      return;
    }

    Array.prototype.forEach.call(currentList.children, function (siblingItem) {
      if (siblingItem === currentItem || !siblingItem.matches("[data-cat-item]")) {
        return;
      }

      var siblingButton = siblingItem.querySelector('[data-cat-toggle][aria-expanded="true"]');
      if (!siblingButton) {
        return;
      }

      var siblingPanelId = siblingButton.getAttribute("aria-controls");
      var siblingPanel = siblingPanelId ? document.getElementById(siblingPanelId) : null;
      siblingButton.setAttribute("aria-expanded", "false");
      if (siblingPanel) {
        siblingPanel.setAttribute("hidden", "");
      }
    });
  }

  function toggleCategoryPanel(button) {
    var panelId = button.getAttribute("aria-controls");
    var panel = panelId ? document.getElementById(panelId) : null;
    if (!panel) {
      return;
    }

    var isExpanded = button.getAttribute("aria-expanded") === "true";
    if (!isExpanded) {
      collapseSiblingCategoryItems(button.closest("[data-cat-item]"));
    }

    button.setAttribute("aria-expanded", isExpanded ? "false" : "true");
    if (isExpanded) {
      panel.setAttribute("hidden", "");
    } else {
      panel.removeAttribute("hidden");
    }
  }

  function toggleAtlasPanel(button) {
    var currentItem = button.closest(".sidebar-category-item");
    if (!currentItem) {
      return;
    }

    var shouldOpen = !currentItem.classList.contains("open");
    var currentList = currentItem.parentElement;
    if (shouldOpen && currentList) {
      Array.prototype.forEach.call(currentList.children, function (siblingItem) {
        if (siblingItem === currentItem || !siblingItem.classList.contains("sidebar-category-item")) {
          return;
        }

        siblingItem.classList.remove("open");
        var siblingButton = siblingItem.querySelector("[data-atlas-toggle]");
        if (siblingButton) {
          siblingButton.setAttribute("aria-expanded", "false");
        }
      });
    }

    currentItem.classList.toggle("open", shouldOpen);
    button.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
  }

  document.addEventListener("click", function (event) {
    var catToggle = event.target.closest("[data-cat-toggle]");
    if (catToggle) {
      event.preventDefault();
      event.stopPropagation();
      toggleCategoryPanel(catToggle);
      return;
    }

    var atlasToggle = event.target.closest("[data-atlas-toggle]");
    if (atlasToggle) {
      event.preventDefault();
      event.stopPropagation();
      toggleAtlasPanel(atlasToggle);
    }
  });
})();
