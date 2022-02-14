M.mod_giportfolio_overflow = {
    init: function () {
        var graph = document.getElementById('graphcontributors');
        graph.parentElement.classList.remove('no-overflow');

        graph.parentElement.classList.add('graphcontributors-overflow');

    }
}