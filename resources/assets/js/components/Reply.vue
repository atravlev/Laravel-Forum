<template>
    <div :id="'reply-'+id" class="card">
        <div class="card-header" :class="isBest ? 'card-success' : ''">
            <div class="level">
                <div>
                    <a :href="'/profiles/'+reply.owner.name" v-text="reply.owner.name">
                    </a> said <span v-text="ago"></span>...
                </div>
                <div v-if="signedIn">
                    <favorite :reply="reply"></favorite>
                </div>
            </div>

        </div>
        <div class="card-body">
            <div v-if="editing">
                <form @submit.prevent="update">
                    <div class="form-group">
                        <wysiwyg v-model="body"></wysiwyg>
                    
                    </div>
                    <button class="btn btn-sm btn-primary">Update</button>
                    <button type="button" class="btn btn-sm btn-link" @click="editing = false">Cancel</button>
                </form>
                
            </div>
            <div v-else v-html="body"></div>
        </div>
        <div class="card-footer level" v-if="authorize('owns', reply) || authorize('owns', reply.thread)">
            <div v-if="authorize('owns', reply)">
                <button class="btn btn-sm" @click="editing = true">Edit</button>
                <button class="btn btn-danger btn-sm" @click="destroy">Delete</button>
            </div>
            <button class="btn btn-primary btn-sm" @click="markBestReply" v-show="! isBest" v-if="authorize('owns', reply.thread)">Best reply?</button>
        </div>
    </div>
</template>

<script>
    import Favorite from './Favorite.vue';
    import moment from 'moment';

    export default {
        props: ['reply'],

        components: { Favorite },

        data() {
            return {
                editing: false,
                id: this.reply.id,
                body: this.reply.body,
                isBest: this.reply.isBest,
            };
        },

        computed: {
            ago() {
                return moment(this.reply.created_at).fromNow();
            }
        },

        created() {
            window.events.$on('best-reply-selected', id => {
                this.isBest = (id === this.id);
            });
        },

        methods: {
            update() {
                axios.patch(
                    '/replies/' + this.id, {
                    body: this.body
                })
                .catch(error => {
                    flash(error.response.data, 'danger');
                });

                this.editing = false;

                flash('Updated !');
            },

            destroy() {
                axios.delete('/replies/' + this.id);

                this.$emit('deleted', this.id);
            },

            markBestReply() {
                axios.post('/replies/'+ this.id +'/best');

                window.events.$emit('best-reply-selected', this.id);
            }
        }
    }
</script>