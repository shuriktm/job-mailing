<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Mailing</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
    <style>
        .form-control[type="number"] {
            width: 46px;
        }
        .form-control[type="number"]::-webkit-inner-spin-button,
        .form-control[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>
<script src="https://unpkg.com/axios@1.0.0/dist/axios.min.js"></script>
<script type="module">
    import {createApp, reactive} from "https://unpkg.com/petite-vue?module"

    const auto = localStorage.getItem('mailing:auto');

    /* App state */
    const store = reactive({
        groups: [],
        data: {
            auto: (auto === null) || !!parseInt(auto),
            interval: localStorage.getItem('mailing:interval') || 10,
        },
        loading: false,
        timeoutID: null,
        load() {
            store.loading = true

            axios.post('/?load=1', {ts: new Date().getTime()})
                .then(function (response) {
                    store.groups = response.data
                    store.auto()
                })
                .catch(function (error) {
                    console.log(error)
                })
                .finally(function () {
                    store.loading = false
                });
        },
        auto() {
            store.timeoutID && clearTimeout(store.timeoutID)
            if (store.data.auto) {
                store.timeoutID = setTimeout(() => store.load(), store.data.interval * 1000)
            }
        },
        interval() {
            localStorage.setItem('mailing:interval', store.data.interval)
            store.auto()
        },
        switch(e) {
            store.data.auto = e.target.checked
            localStorage.setItem('mailing:auto', store.data.auto ? 1 : 0)
            store.auto()
        },
    })

    /* Preload data */
    store.load()

    /* Init Vue components */
    createApp({store}).mount('#app')
</script>
<div class="container" v-scope id="app">
    <nav class="navbar bg-dark shadow rounded mt-4 border">
        <div class="container-fluid py-2 px-2">
            <span class="navbar-brand mb-0 px-3 h3 text-light">
                Test Mailing
            </span>
            <div class="d-flex">
                <button v-if="store.loading" class="btn btn-primary text-nowrap" type="button" disabled>
                    <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
                    Loading...
                </button>
                <button v-else @click="store.load" class="btn btn-primary px-4" type="button">Refresh</button>
                <div class="input-group mb-0 mx-3">
                    <div class="input-group-text">
                        <div class="form-check form-switch">
                            <input v-model="store.data.auto" @input="store.switch" class="form-check-input" type="checkbox" role="switch" id="auto-refresh" :disabled="store.loading">
                            <label class="form-check-label" for="auto-refresh">Auto-refresh in</label>
                        </div>
                    </div>
                    <input v-model="store.data.interval" @change="store.interval" type="number" class="form-control" placeholder="Interval" aria-label="Refresh interval" :disabled="store.loading" min="10" max="60">
                    <span class="input-group-text">seconds</span>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mt-5" v-scope="{ localCount: 0 }">
        <div class="row gx-3">
            <template v-for="group of store.groups">
                <div class="col">
                    <div class="card shadow-sm">
                        <div v-if="group.color" :class="`card-body bg-${group.color} bg-opacity-10`">
                            <h5 class="card-title">{{ group.name }}</h5>
                            <small class="card-text">{{ group.text }}</small>
                        </div>
                        <div v-else class="card-body bg-light">
                            <h5 class="card-title">{{ group.name }}</h5>
                            <small class="card-text">{{ group.text }}</small>
                        </div>
                        <ul class="list-group list-group-flush">
                            <template v-for="item of group.items">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">{{ item.label }}</div>
                                        <small class="text-muted">{{ item.caption }}</small>
                                    </div>
                                    <span v-if="group.color" :class="`badge bg-${group.color} bg-opacity-60 user-select-all`">{{ item.qty }}</span>
                                    <span v-else :class="`badge bg-secondary bg-opacity-60 user-select-all`">{{ item.qty }}</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
</body>
</html>
