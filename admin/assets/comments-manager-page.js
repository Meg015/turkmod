var editModalController = null;
var COMMENT_READ_MORE_LIMIT = 160;

function initCommentReadMore() {
    document.querySelectorAll('[data-ui-comment-manager-body]').forEach(function (body) {
        if (body.getAttribute('data-ui-read-more-ready') === '1') {
            return;
        }

        body.setAttribute('data-ui-read-more-ready', '1');
        if (body.scrollHeight <= COMMENT_READ_MORE_LIMIT) {
            return;
        }

        body.classList.add('is-truncated');

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ui-comment-manager-read-more-btn';
        toggle.setAttribute('aria-controls', body.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = 'Devamını Oku...';

        toggle.addEventListener('click', function () {
            var isTruncated = body.classList.toggle('is-truncated');
            toggle.setAttribute('aria-expanded', isTruncated ? 'false' : 'true');
            toggle.textContent = isTruncated ? 'Devamını Oku...' : 'Daha Az Göster';

            if (isTruncated) {
                body.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        body.insertAdjacentElement('afterend', toggle);
    });
}

function openEditModal(commentId, commentBody) {
    document.getElementById('editCommentId').value = commentId;
    document.getElementById('editCommentBody').value = commentBody;
    var modal = document.getElementById('editModal');
    document.body.style.overflow = 'hidden';

    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        editModalController = window.TMUI.openDialog(modal, {
            openClass: 'active',
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: '#editCommentBody',
            returnFocus: document.activeElement,
            onClose: function () {
                editModalController = null;
                document.body.style.overflow = '';
            }
        });
        return;
    }

    modal.hidden = false;
    modal.classList.add('active');
    document.getElementById('editCommentBody').focus();
}

function closeEditModal() {
    var modal = document.getElementById('editModal');
    if (editModalController && typeof editModalController.close === 'function') {
        editModalController.close(true);
        return;
    }
    modal.classList.remove('active');
    modal.hidden = true;
    document.body.style.overflow = '';
}

document.addEventListener('click', function(event) {
    const editTrigger = event.target.closest('[data-comment-edit]');
    if (!editTrigger) return;
    openEditModal(editTrigger.getAttribute('data-comment-edit'), editTrigger.getAttribute('data-comment-body') || '');
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (!window.TMUI && e.key === 'Escape') {
        closeEditModal();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCommentReadMore);
} else {
    initCommentReadMore();
}
