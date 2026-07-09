<script>
import CountUp from 'vue-countup-v3'

export default {
    props: {
        list: Object,
        jobsCard: Boolean,
        cardClass: String,
        bodyClass: String,
        listCard:Boolean,
        titleClass:String,
        NoCountUp:Boolean,
        CountUp:Boolean
    },
    components: {
        CountUp
    }
}
</script>

<template>
<div :class="`custom-card ${cardClass}`">
    <div :class="`card-body ${bodyClass}`">
        <div :class="`d-flex align-items-start gap-3 flex-wrap ${list.flexClass}`">
            <div class="flex-fill">
                <span :class="titleClass" class="fs-13 fw-medium">{{ list.title }}</span>
                <div class="pb-0 mt-0">
                    <!-- listCard content -->
                    <div v-if="listCard">
                        <span class="fs-13 fw-medium">{{ list.titles }}</span>
                        <div :class="`d-flex align-items-center gap-2 my-2  ${list.ValueClass1}`">
                            <h4 :class="`fw-medium mb-0 custom-dashboard ${list.ValueClass}`">
                                <span class="count-up" v-if="NoCountUp">{{ list.count }}</span>
                                <span class="count-up" v-if="CountUp"><count-up :end-val="list.count" /></span>{{ list.countK ? 'K' :'' }}
                            </h4>
                            <span :class="`badge bg-${list.priceColor}`">
                                {{ list.price }}
                            </span>
                        </div>
                        <p :class="`text-muted fs-12 mb-0 lh-1 ${list.smallText}`">
                            <span :class="`text-${list.iconColor} me-1 d-inline-block ${list.percentColor}`">
                                <i :class="list.icon"></i>
                                {{ list.percent }}
                            </span>
                            <span class="monthly-percent">this month</span>
                        </p>
                    </div>

                    <!-- jobsCard content -->
                    <div v-if="jobsCard" class="d-flex align-items-center gap-2">
                        <h3 class="fw-semibold mb-0">{{ list.count }}</h3>
                        <span :class="`text-${list.iconColor}`">
                            <i :class="list.icon"></i>{{ list.percent }}
                        </span>
                    </div>
                </div>
            </div>

            <div :class="`avatar avatar-md ${list.avatarClass} bg-${list.priceColor}-transparent mb-3 svg-${list.priceColor} `" v-html="list.svgIcon"></div>
        </div>
    </div>
</div>
</template>

<style scoped>
/* Add any necessary styles here */
</style>
